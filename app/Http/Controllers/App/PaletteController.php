<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The Ctrl+K palette's people: this business's employees (current first, then leavers), fetched once when
 * the palette is first opened and filtered in the browser. Small businesses: a few dozen rows at most.
 */
class PaletteController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $employees = $request->user()->business->employees()
            ->with('workSite:id,name')
            ->orderByRaw('ended_on is not null')
            ->orderBy('full_name')
            ->limit(500)
            ->get(['id', 'full_name', 'job_title', 'work_site_id', 'ended_on'])
            ->map(fn (Employee $e) => [
                'id' => $e->id,
                'name' => $e->full_name,
                'detail' => $e->ended_on ? 'Left '.$e->ended_on->format('j M Y') : collect([$e->job_title, $e->workSite?->name])->filter()->implode(' · '),
                'leaver' => $e->ended_on !== null,
            ]);

        return response()->json(['employees' => $employees]);
    }
}
