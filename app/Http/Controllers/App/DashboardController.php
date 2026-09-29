<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $business = $request->user()->business;
        $employees = $business->employees()->current()->count();

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
