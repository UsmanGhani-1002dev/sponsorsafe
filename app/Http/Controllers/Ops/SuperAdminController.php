<?php

namespace App\Http\Controllers\Ops;

use App\Http\Controllers\Controller;
use App\Models\SuperAdmin;
use App\Services\PasswordChange;
use App\Services\SuperAdmins;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Super admins: the list, add (emailed invite), resend, remove; and My account (change password). */
class SuperAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $me = $request->user('ops');

        return Inertia::render('Ops/SuperAdmins', [
            'base' => '/'.config('sponsorsafe.ops_path'),
            'admins' => SuperAdmin::orderBy('id')->get()->map(fn (SuperAdmin $a) => [
                'id' => $a->id,
                'name' => $a->name,
                'email' => $a->email,
                'you' => $a->is($me),
                'status' => match (true) {
                    $a->last_login_at !== null => 'Last signed in '.$a->last_login_at->format('j M Y'),
                    $a->invited_at !== null => 'Invite sent '.$a->invited_at->format('j M Y'),
                    default => 'Not signed in yet',
                },
                'invited' => $a->last_login_at === null && $a->invited_at !== null,
            ]),
            'allowedIps' => array_values(array_filter(config('sponsorsafe.ops_allowed_ips', []))),
            'yourIp' => $request->ip(),
        ]);
    }

    public function store(Request $request, SuperAdmins $admins): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:255'], 'email' => ['required', 'email', 'max:255']]);
        $admin = $admins->invite($data['name'], $data['email'], $request->user('ops'));

        return back()->with('success', "Invite sent to {$admin->email}.");
    }

    public function resend(Request $request, SuperAdmin $admin, SuperAdmins $admins): RedirectResponse
    {
        $admins->resend($admin, $request->user('ops'));

        return back()->with('success', "A new invite was sent to {$admin->email}.");
    }

    public function destroy(Request $request, SuperAdmin $admin, SuperAdmins $admins): RedirectResponse
    {
        $admins->remove($admin, $request->user('ops'));

        return back()->with('success', "{$admin->name} can no longer sign in.");
    }

    public function account(Request $request): Response
    {
        $me = $request->user('ops');

        return Inertia::render('Ops/Account', [
            'base' => '/'.config('sponsorsafe.ops_path'),
            'account' => ['name' => $me->name, 'email' => $me->email, 'needsCode' => PasswordChange::needsCode($me), 'minLength' => PasswordChange::minLength($me)],
        ]);
    }

    public function password(Request $request, PasswordChange $passwords): RedirectResponse
    {
        $passwords->change($request, $request->user('ops'));

        return back()->with('success', 'Password changed. Any other devices have been signed out.');
    }
}
