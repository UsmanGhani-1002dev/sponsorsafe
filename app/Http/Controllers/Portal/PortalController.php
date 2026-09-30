<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use Illuminate\Http\Request;

/** Base for the employee portal: everything is scoped to the signed-in employee's own record. */
abstract class PortalController extends Controller
{
    protected function employee(Request $request): Employee
    {
        $user = $request->user();
        $employee = $user->employee()->first();
        abort_if(! $employee, 403, 'Your employee record is not set up yet. Please contact your employer.');

        return $employee->setRelation('business', $user->business)->setRelation('user', $user);
    }

    /** A request as the employee sees it in "My requests". */
    protected static function requestRow(EmployeeRequest $r): array
    {
        return [
            'id' => 'r'.$r->id,
            'kind' => $r->kind === 'document' && $r->document_request_id ? 'Document requested by HR' : $r->kindLabel(),
            'summary' => $r->summary(),
            'sent' => $r->created_at->format('j M Y'),
            'status' => $r->statusBadge(),
            'hrNote' => $r->hr_note,
        ];
    }

    /** A document HR has asked for and not yet received: "Action needed". */
    protected static function actionRow(DocumentRequest $d): array
    {
        return [
            'id' => 'd'.$d->id,
            'kind' => 'Document requested by HR',
            'summary' => $d->category->label(),
            'sent' => $d->created_at->format('j M Y'),
            'status' => ['text' => 'Action needed', 'tone' => 'amber'],
            'hrNote' => 'Please upload this under My documents.',
        ];
    }

    /** HR requests still waiting for this employee, excluding any they have already sent a file for. */
    protected function awaiting(Employee $e)
    {
        $sent = $e->requests()->pending()->whereNotNull('document_request_id')->pluck('document_request_id');

        return $e->documentRequests()->where('status', DocumentRequest::STATUS_AWAITING)->whereNotIn('id', $sent)->orderBy('created_at')->get();
    }
}
