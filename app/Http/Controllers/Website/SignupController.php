<?php

namespace App\Http\Controllers\Website;

use App\Billing\Gateways;
use App\Billing\PayPalException;
use App\Billing\PayPalGateway;
use App\Billing\StripeGateway;
use App\Billing\Subscriptions;
use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\User;
use App\Support\FormToken;
use App\Support\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Stripe\Exception\ApiErrorException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Website sign-up: business details → pending business → Stripe Checkout or PayPal approval (both hosted,
 * so card details never touch our server) → back here, where the payment is confirmed with the gateway
 * and the admin is emailed a set-password link. The webhook confirms the same payment too; whichever
 * arrives first wins.
 */
class SignupController extends Controller
{
    public const BANDS = ['1-5' => '1–5', '6-10' => '6–10', '11-15' => '11–15', '16+' => 'More than 15'];

    public function show(Request $request): Response
    {
        $pending = $this->pendingBusiness($request);
        $admin = $pending?->admins()->orderBy('id')->first();

        return Inertia::render('Website/Signup', [
            'plan' => Pricing::forDisplay(),
            'bands' => collect(self::BANDS)->map(fn ($label, $value) => ['value' => $value, 'label' => $label])->values(),
            'formToken' => FormToken::issue(),
            'gateways' => ['card' => Gateways::stripeReady(), 'paypal' => Gateways::paypalReady()],
            'cancelled' => $request->boolean('cancelled') && $pending !== null,
            // Coming back from a cancelled payment: keep what they typed.
            'previous' => $pending ? [
                'business' => $pending->name, 'licence' => $pending->licence_number, 'name' => $admin?->name,
                'email' => $admin?->email, 'phone' => $pending->phone, 'employees' => $pending->employees_band,
            ] : null,
        ]);
    }

    public function store(Request $request, Subscriptions $subscriptions, StripeGateway $stripe, PayPalGateway $paypal): HttpResponse
    {
        if ($request->filled('website') || ! FormToken::human($request->input('form_token'))) {
            throw ValidationException::withMessages(['form' => 'Sorry, we could not send that. Please try again in a moment.']);
        }
        $data = $request->validate([
            'business' => ['required', 'string', 'max:160'],
            'licence' => ['required', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'employees' => ['required', Rule::in(array_keys(self::BANDS))],
            'pay' => ['required', Rule::in(['card', 'paypal'])],
            'agree' => ['accepted'],
        ], [
            'business.required' => 'Please add your business name.',
            'licence.required' => 'Please add your sponsor licence number.',
            'name.required' => 'Please add your name.',
            'email.required' => 'Please add your email.',
            'email.email' => 'Please add a valid email.',
            'agree.accepted' => 'Please agree to the terms and privacy policy.',
        ]);

        $limit = Pricing::current()['employee_limit'];
        if ($data['employees'] === '16+') {
            throw ValidationException::withMessages(['employees' => "The plan covers up to {$limit} employees. Please contact us for a larger plan."]);
        }
        $existing = User::with('business')->where('email', mb_strtolower(trim($data['email'])))->first();
        if ($existing && ! $existing->business?->isPending()) {
            throw ValidationException::withMessages(['email' => 'This email already has an account. Log in instead, or use a different email.']);
        }
        if ($data['pay'] === 'paypal') {
            return $this->toPaypal($request, $data, $subscriptions, $paypal);
        }
        if (! Gateways::stripeReady()) {
            throw ValidationException::withMessages(['pay' => 'Card payments are not switched on yet. Please contact us to subscribe.']);
        }

        $business = $subscriptions->start($data, 'stripe');
        $request->session()->put('signup_business_id', $business->id);

        try {
            $url = $stripe->checkoutUrl(
                $business,
                route('signup.done').'?session_id={CHECKOUT_SESSION_ID}',
                route('signup', ['cancelled' => 1]),
            );
        } catch (ApiErrorException $e) {
            Log::warning('Stripe Checkout could not start', ['business' => $business->id, 'error' => $e->getMessage()]);
            throw ValidationException::withMessages(['form' => 'We could not reach the payment provider. Please try again in a moment.']);
        }

        return Inertia::location($url);
    }

    /** Stripe sends people back here after paying. The payment is checked with Stripe, never trusted from the URL. */
    public function done(Request $request, Subscriptions $subscriptions, StripeGateway $stripe): Response|RedirectResponse
    {
        $sessionId = (string) $request->query('session_id');
        if (! str_starts_with($sessionId, 'cs_')) {
            return redirect()->route('signup');
        }
        try {
            $result = $stripe->checkoutResult($sessionId);
        } catch (ApiErrorException $e) {
            Log::warning('Stripe Checkout could not be confirmed', ['session' => $sessionId, 'error' => $e->getMessage()]);
            $result = null;
        }
        $business = $result ? Business::find($result['business_id']) : $this->pendingBusiness($request);
        if ($result && $business) {
            $subscriptions->paid($business, 'stripe', $result['label'], $result['next']);
            $request->session()->forget('signup_business_id');
        }

        return $this->donePage($business, (bool) ($result && $business));
    }

    /** Approved on PayPal: they come back with ?subscription_id=I-…, which is checked with PayPal. */
    public function paypalDone(Request $request, Subscriptions $subscriptions, PayPalGateway $paypal): Response|RedirectResponse
    {
        $id = (string) $request->query('subscription_id');
        if (! str_starts_with($id, 'I-')) {
            return redirect()->route('signup');
        }
        $business = Business::where('paypal_subscription_id', $id)->first() ?? $this->pendingBusiness($request);
        $confirmed = false;
        try {
            $sub = $paypal->subscription($id);
            // Only the subscription we created for this business, and only once PayPal says it is running.
            if ($business && $sub['business_id'] === $business->id && $business->paypal_subscription_id === $id && $sub['status'] === 'ACTIVE') {
                $subscriptions->paid($business, 'paypal', 'PayPal', $sub['next']);
                $request->session()->forget('signup_business_id');
                $confirmed = true;
            }
        } catch (PayPalException $e) {
            Log::warning('PayPal subscription could not be confirmed', ['subscription' => $id, 'error' => $e->getMessage()]);
        }

        return $this->donePage($business, $confirmed);
    }

    /** PayPal: start a subscription and send the visitor to PayPal to approve it. */
    private function toPaypal(Request $request, array $data, Subscriptions $subscriptions, PayPalGateway $paypal): HttpResponse
    {
        if (! Gateways::paypalReady()) {
            throw ValidationException::withMessages(['pay' => 'PayPal is not switched on yet. Please pay by card, or contact us to subscribe.']);
        }
        $business = $subscriptions->start($data, 'paypal');
        $request->session()->put('signup_business_id', $business->id);

        try {
            [$id, $url, $plan] = $paypal->createSubscription($business, route('signup.paypal.done'), route('signup', ['cancelled' => 1]));
        } catch (PayPalException $e) {
            Log::warning('PayPal subscription could not start', ['business' => $business->id, 'error' => $e->getMessage()]);
            throw ValidationException::withMessages(['form' => 'We could not reach PayPal. Please try again in a moment, or pay by card.']);
        }
        $business->update(['paypal_subscription_id' => $id, 'paypal_plan_id' => $plan]);

        return Inertia::location($url);
    }

    private function donePage(?Business $business, bool $confirmed): Response
    {
        $admin = $business?->admins()->orderBy('id')->first();

        return Inertia::render('Website/SignupDone', [
            'confirmed' => $confirmed,
            'first' => $admin ? strtok($admin->name, ' ') : null,
            'business' => $business?->name,
            'email' => $admin?->email,
        ]);
    }

    private function pendingBusiness(Request $request): ?Business
    {
        $id = $request->session()->get('signup_business_id');

        return $id ? Business::where('status', Business::PENDING)->find($id) : null;
    }
}
