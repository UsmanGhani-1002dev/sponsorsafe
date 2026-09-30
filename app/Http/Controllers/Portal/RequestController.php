<?php

namespace App\Http\Controllers\Portal;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** "My requests": everything sent to HR, plus documents HR is waiting for (Action needed). */
class RequestController extends PortalController
{
    public function __invoke(Request $request): Response
    {
        $e = $this->employee($request);

        return Inertia::render('Portal/Requests', [
            'rows' => [
                ...$this->awaiting($e)->map(fn ($d) => self::actionRow($d))->all(),
                ...$e->requests()->with('document')->latest()->get()->map(fn ($r) => self::requestRow($r))->all(),
            ],
        ]);
    }
}
