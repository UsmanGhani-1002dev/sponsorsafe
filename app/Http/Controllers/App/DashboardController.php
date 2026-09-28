<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $business = $request->user()->business;
        $employees = $business->users()->where('role', User::ROLE_EMPLOYEE)->where('active', true)->count();

        return Inertia::render('App/Dashboard', [
            'business' => [
                'name' => $business->name,
                'licence' => $business->licence_number,
                'employees' => $employees,
                'limit' => $business->employee_limit,
            ],
        ]);
    }
}
