<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Enquiry;
use App\Notifications\NewEnquiry;
use App\Support\FormToken;
use App\Support\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** The public website: home page (features, pricing, training, FAQ, contact) and the legal pages. */
class WebsiteController extends Controller
{
    public function home(Request $request): Response
    {
        return Inertia::render('Website/Home', [
            'plans' => Pricing::forDisplay(),
            'topics' => Enquiry::TOPICS,
            'topic' => in_array($request->query('topic'), Enquiry::TOPICS, true) ? $request->query('topic') : Enquiry::TOPICS[0],
            'formToken' => FormToken::issue(),
            // Only a business or employee login gets "Go to your account" (never the super admin guard).
            'signedIn' => $request->user('web')?->homeRoute(),
        ]);
    }

    public function contact(Request $request): RedirectResponse
    {
        // Bot protection: a hidden field people never fill in, and a signed start time.
        if ($request->filled('website') || ! FormToken::human($request->input('form_token'))) {
            throw ValidationException::withMessages(['message' => 'Sorry, we could not send that. Please try again in a moment.']);
        }
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'topic' => ['required', Rule::in(Enquiry::TOPICS)],
            'message' => ['required', 'string', 'max:3000'],
        ], ['name.required' => 'Please add your name.', 'email.required' => 'Please add your email.', 'message.required' => 'Please add a message.']);

        $enquiry = Enquiry::create([...$data, 'source' => 'website', 'ip' => $request->ip()]);
        Notification::route('mail', config('sponsorsafe.support_email'))->notify(new NewEnquiry($enquiry));

        return redirect(route('home').'#contact')->with('contactSent', ['first' => strtok($data['name'], ' '), 'email' => $data['email']]);
    }

    public function legal(string $page): Response
    {
        return Inertia::render('Website/Legal', ['page' => $page]);
    }
}
