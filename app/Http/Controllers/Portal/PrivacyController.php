<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Privacy notice for employees (product-decisions §11): what the employer keeps, why, who sees it, for how
 * long, and their rights. Draft wording until Enovtec supplies the final text. Retention periods come from
 * this business's own settings, so the notice matches what actually happens.
 */
class PrivacyController extends PortalController
{
    public function __invoke(Request $request): Response
    {
        $employee = $this->employee($request);
        $business = $employee->business;

        return Inertia::render('Portal/Privacy', [
            'business' => $business->name,
            'contacts' => $business->admins()->orderBy('id')->pluck('email'),
            'retentionYears' => (int) $business->rule('retention_years'),
            'rtwRetentionYears' => (int) $business->rule('rtw_retention_years'),
            'sponsored' => $employee->isSponsored(),
        ]);
    }
}
