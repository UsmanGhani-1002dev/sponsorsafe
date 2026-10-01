<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PasswordChange;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Change password while signed in: business admins (Settings) and employees (My details). */
class PasswordController extends Controller
{
    public function __invoke(Request $request, PasswordChange $passwords): RedirectResponse
    {
        $passwords->change($request, $request->user());

        return back()->with('success', 'Password changed. Any other devices have been signed out.');
    }
}
