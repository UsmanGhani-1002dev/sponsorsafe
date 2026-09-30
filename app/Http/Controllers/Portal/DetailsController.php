<?php

namespace App\Http\Controllers\Portal;

use App\Models\EmployeeRequest;
use App\Services\EmployeeRequests;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use PragmaRX\Google2FA\Google2FA;

/** "My details", "Update my details" (sent to HR) and optional two-step sign-in for employees. */
class DetailsController extends PortalController
{
    public function show(Request $request, Google2FA $g2fa): Response
    {
        $e = $this->employee($request)->load('workSite');
        $user = $request->user();
        $v = fn ($x) => $x ?: '—';

        return Inertia::render('Portal/Details', [
            'sections' => [
                ['title' => 'Personal', 'fields' => [
                    ['label' => 'Full legal name', 'value' => $e->full_name],
                    ['label' => 'Date of birth', 'value' => $v($e->date_of_birth?->format('j M Y'))],
                    ['label' => 'Home address', 'value' => $v($e->address)],
                    ['label' => 'Phone', 'value' => $v($e->phone)],
                    ['label' => 'Email', 'value' => $e->email],
                    ['label' => 'National Insurance number', 'value' => $v($e->masked('ni_number'))],
                ]],
                ['title' => 'Job', 'fields' => [
                    ['label' => 'Job title', 'value' => $e->job_title],
                    ['label' => 'Work site', 'value' => $v($e->workSite?->name)],
                    ['label' => 'Start date', 'value' => $e->start_date->format('j M Y')],
                    ['label' => 'Contracted hours', 'value' => rtrim(rtrim((string) $e->contracted_hours, '0'), '.').' per week'],
                    ['label' => 'Contract type', 'value' => $e->contract_type],
                ]],
                ['title' => 'Right to work', 'fields' => [
                    ['label' => 'Status', 'value' => $e->rtw_basis->timeLimited() ? ($e->visa_type ?: $e->rtw_basis->label()) : $e->rtw_basis->label()],
                    ['label' => 'Expiry', 'value' => $e->rtw_basis->timeLimited() ? $v($e->visa_expiry?->format('j M Y')) : 'No time limit'],
                    ['label' => 'Last checked', 'value' => $e->rtw_check_date->format('j M Y')],
                ]],
            ],
            'twoFactor' => [
                'enabled' => $user->two_factor_confirmed_at !== null,
                'setup' => $user->two_factor_secret && ! $user->two_factor_confirmed_at ? [
                    'secret' => trim(chunk_split($user->two_factor_secret, 4, ' ')),
                    'otpauth' => $g2fa->getQRCodeUrl(config('app.name'), $user->email, $user->two_factor_secret),
                ] : null,
            ],
        ]);
    }

    public function editChange(Request $request): Response
    {
        $e = $this->employee($request);
        $kinds = collect(EmployeeRequest::CHANGE_KINDS)->when(! $e->rtw_basis->timeLimited(), fn ($c) => $c->except('visa'));

        return Inertia::render('Portal/Change', [
            'kinds' => $kinds->map(fn ($k, $value) => ['value' => $value, 'label' => $k['label'], 'field' => $k['field']])->values(),
            'preselect' => $kinds->has((string) $request->query('kind')) ? $request->query('kind') : 'address',
            'current' => ['address' => $e->address, 'phone' => $e->phone, 'email' => $e->email, 'name' => $e->full_name, 'visa' => $e->visa_expiry?->format('j M Y')],
        ]);
    }

    public function storeChange(Request $request, EmployeeRequests $requests): RedirectResponse
    {
        $e = $this->employee($request);
        $kind = $request->validate(['kind' => ['required', Rule::in(array_keys(EmployeeRequest::CHANGE_KINDS))]])['kind'];
        abort_if($kind === 'visa' && ! $e->rtw_basis->timeLimited(), 422);
        $field = EmployeeRequest::CHANGE_KINDS[$kind]['field'];
        $data = $request->validate([
            'value' => match ($kind) {
                'email' => ['required', 'email', 'max:255'],
                'visa' => ['required', 'date', 'after:today'],
                'address' => ['required', 'string', 'max:500'],
                default => ['required', 'string', 'max:255'],
            },
            'note' => ['nullable', 'string', 'max:300'],
        ], ['value.required' => 'Please fill in '.mb_strtolower($field).'.', 'value.after' => 'The new expiry date must be in the future.'], ['value' => mb_strtolower($field)]);

        $requests->change($e, $kind, trim($data['value']), $data['note'] ?? null, $request->user());

        return redirect()->route('portal.requests')->with('success', 'Sent to HR. You can follow it here.');
    }

    /** Optional for employees: start setting up an authenticator app. */
    public function startTwoFactor(Request $request, Google2FA $g2fa): RedirectResponse
    {
        $user = $request->user();
        if (! $user->two_factor_confirmed_at) {
            $user->forceFill(['two_factor_secret' => $g2fa->generateSecretKey(32)])->save();
        }

        return back();
    }

    public function confirmTwoFactor(Request $request, Google2FA $g2fa): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $request->user();
        if (! $user->two_factor_secret || ! $g2fa->verifyKey($user->two_factor_secret, preg_replace('/\D/', '', (string) $request->input('code')), 1)) {
            throw ValidationException::withMessages(['code' => 'That code is not right. Use the current 6-digit code from your authenticator app.']);
        }
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        Audit::log('auth.two_factor_enabled', $user, actor: $user);

        return back()->with('success', 'Two-step sign-in is on. You will be asked for a code each time you sign in.');
    }

    public function disableTwoFactor(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string']]);
        $user = $request->user();
        if (! Hash::check((string) $request->input('password'), $user->password)) {
            throw ValidationException::withMessages(['password' => 'That password is not right.']);
        }
        $user->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        Audit::log('auth.two_factor_disabled', $user, actor: $user);

        return back()->with('success', 'Two-step sign-in is off.');
    }
}
