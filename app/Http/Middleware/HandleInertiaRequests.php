<?php

namespace App\Http\Middleware;

use App\Models\Enquiry;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        $user = $request->user('web');

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'auth' => [
                'user' => $user ? [
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'initials' => $user->initials(),
                    'business' => $user->business?->name,
                ] : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'contactSent' => fn () => $request->session()->get('contactSent'),
            ],
            // Business admins: a failed payment is in its grace period (banner with "Manage billing").
            'billing' => fn () => $user?->isAdmin() && $user->business?->inGrace()
                ? ['graceEnds' => $user->business->grace_ends_on->format('j M Y')]
                : null,
            // Super admin sidebar: new enquiries waiting (only for a signed-in super admin).
            'ops' => fn () => auth('ops')->check() ? ['newEnquiries' => Enquiry::where('status', Enquiry::NEW)->count()] : null,
        ];
    }
}
