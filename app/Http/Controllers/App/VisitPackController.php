<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Services\VisitPack;
use App\Support\Audit;
use Illuminate\Http\Request;

/** "Prepare for a Home Office visit": one PDF for the whole business. Audited. */
class VisitPackController extends Controller
{
    public function __invoke(Request $request, VisitPack $pack): \Illuminate\Http\Response
    {
        $business = $request->user()->business;
        Audit::log('employee.visit_pack_exported', $business, ['employees' => $business->employees()->current()->count()], $request->user());

        return $pack->download($business, $request->user());
    }
}
