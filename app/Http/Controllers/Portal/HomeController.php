<?php

namespace App\Http\Controllers\Portal;

use App\Services\WorkingDays;
use App\Support\LeaveBalance;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Portal home: leave left, documents HR needs, right-to-work status, quick actions and recent requests. */
class HomeController extends PortalController
{
    public function __invoke(Request $request): Response
    {
        $e = $this->employee($request);
        $days = $e->visa_expiry ? (int) today()->diffInDays($e->visa_expiry, false) : null;

        return Inertia::render('Portal/Home', [
            'first' => strtok($e->full_name, ' '),
            'business' => $e->business->name,
            'leave' => LeaveBalance::for($e, WorkingDays::fromDatabase()),
            'documentsNeeded' => $this->awaiting($e)->count(),
            'rightToWork' => $e->rtw_basis->timeLimited()
                ? [
                    'title' => ($e->visa_type ?: 'Permission').' until '.$e->visa_expiry?->format('j M Y'),
                    'note' => $days !== null && $days <= 90
                        ? "Your permission ends in {$days} days. If you have applied to extend it, tell HR under Update my details."
                        : 'Tell HR straight away if your visa changes or you apply to extend it.',
                    'tone' => $days !== null && $days <= 90 ? 'amber' : 'grey',
                ]
                : ['title' => 'No time limit', 'note' => 'No action needed.', 'tone' => 'green'],
            'recent' => $e->requests()->with('document')->latest()->limit(3)->get()->map(fn ($r) => self::requestRow($r)),
        ]);
    }
}
