<?php

namespace App\Services;

use App\Models\Business;
use App\Models\DocumentRequest;
use App\Models\Employee;
use App\Models\EmployeeRequest;
use App\Models\Reminder;
use App\Models\User;
use App\Notifications\EmployeeReminder;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Reminders emailed to employees themselves (alongside the admins' digest in `Reminders`), so the person who has to
 * act hears about it too:
 *   visa / permission expiry — at each lead time in `expiry_alert_days` (90, 60, 30) and when expired, asking for the
 *                              new visa and share code; not while a new-visa request is already waiting for HR
 *   document HR asked for    — straight away when HR asks, then after 7 and 14 days while it is still not sent
 * Only current employees with an active portal login (they have to sign in to act). Each stage is emailed once,
 * recorded in `reminders` like the admins' ones ("60@2026-12-10", "requested@2026-11-24").
 */
class EmployeeReminders
{
    public const VISA = 'employee_visa';
    public const DOCUMENT = 'employee_document';

    /** Days after the request when a document still not sent is chased again. */
    public const DOCUMENT_CHASE_DAYS = [7, 14];

    /**
     * What this employee needs to do now, soonest first.
     *
     * @return Collection<int, array{type: string, subject: string, stage: string, date: CarbonInterface, title: string, text: string, href: string}>
     */
    public function current(Employee $employee): Collection
    {
        $items = collect();
        $today = today();
        $business = $employee->business;

        if ($employee->visa_expiry && ! $this->visaRequestWaiting($employee)) {
            $days = (int) $today->diffInDays($employee->visa_expiry, false);
            $reached = collect((array) $business->rule('expiry_alert_days'))->map(fn ($d) => (int) $d)->filter(fn ($s) => $days <= $s);
            $date = Employee::formatDate($employee->visa_expiry);
            $href = route('portal.change', ['kind' => 'visa']);
            $how = 'send your new visa expiry date in the portal (Update my details → New visa or extension), with your new share code in the note, so '
                .$business->name.' can check your right to work again.';

            if ($days < 0) {
                $items->push($this->item(self::VISA, "employee:{$employee->id}", 'expired', $employee->visa_expiry, 'Please send your new share code',
                    "Your visa or permission to work in the UK ended on {$date}. If you have a new visa or extension, {$how} If not, please speak to {$business->name} today.", $href));
            } elseif ($reached->isNotEmpty()) {
                $items->push($this->item(self::VISA, "employee:{$employee->id}", (string) $reached->min(), $employee->visa_expiry, 'Please send your new share code',
                    "Your visa or permission to work in the UK ends on {$date} (".($days === 1 ? '1 day' : "{$days} days").'). When you get your new visa or extension, '.$how, $href));
            }
        }

        $requests = $employee->documentRequests->where('status', DocumentRequest::STATUS_AWAITING)->sortBy('id')
            ->reject(fn (DocumentRequest $r) => $this->uploadWaiting($employee, $r));
        foreach ($requests as $r) {
            $age = (int) $r->created_at->copy()->startOfDay()->diffInDays($today);
            $chase = collect(self::DOCUMENT_CHASE_DAYS)->filter(fn ($d) => $age >= $d)->max();
            $what = mb_strtolower($r->category->label());
            $items->push($this->item(self::DOCUMENT, "document_request:{$r->id}", $chase ? (string) $chase : 'requested', $r->created_at,
                "Please upload your {$what}",
                "{$business->name} has asked you for your {$what}".($chase ? ' (asked on '.Employee::formatDate($r->created_at).')' : '')
                .'. Please upload it in the portal under My documents.', route('portal.documents')));
        }

        return $items->sortBy(fn ($i) => $i['date']->format('Y-m-d'))->values();
    }

    /**
     * Daily (`reminders:send`): one email per employee with anything new. Returns the number of employees emailed.
     */
    public function send(): int
    {
        $emailed = 0;
        Business::where('status', Business::ACTIVE)->orderBy('id')->each(function (Business $business) use (&$emailed) {
            $sent = $this->sent($business);
            $this->eligible($business)->each(function (Employee $employee) use (&$emailed, $sent) {
                $emailed += (int) $this->sendTo($employee, $sent);
            });
        });

        return $emailed;
    }

    /** HR has just asked for a document: tell the employee now rather than the next morning. */
    public function documentRequested(DocumentRequest $request): void
    {
        $business = Business::findOrFail($request->business_id);
        $employee = $this->eligible($business, $request->employee_id)->first();
        if ($employee) {
            $this->sendTo($employee, $this->sent($business), "document_request:{$request->id}");
        }
    }

    /** Stages already emailed to this business's employees, as "type|subject|stage" keys. */
    private function sent(Business $business): Collection
    {
        return Reminder::where('business_id', $business->id)->whereIn('type', [self::VISA, self::DOCUMENT])->get(['type', 'subject', 'stage'])
            ->map(fn (Reminder $r) => "{$r->type}|{$r->subject}|{$r->stage}")->flip();
    }

    /** Email this employee whatever is new (only one subject, when given). True if an email went. */
    private function sendTo(Employee $employee, Collection $sent, ?string $onlySubject = null): bool
    {
        $items = $this->current($employee)
            ->when($onlySubject, fn ($c) => $c->where('subject', $onlySubject))
            ->reject(fn ($i) => $sent->has("{$i['type']}|{$i['subject']}|{$i['stage']}"))
            ->values();
        if ($items->isEmpty()) {
            return false;
        }

        $employee->user->notify(new EmployeeReminder($employee->business->name, $items->all()));
        Reminder::insert($items->map(fn ($i) => [
            'business_id' => $employee->business_id, 'type' => $i['type'], 'subject' => $i['subject'], 'stage' => $i['stage'], 'sent_at' => now(),
        ])->all());

        return true;
    }

    /** Current employees of an active business who can sign in to the portal, with what the reminders need. */
    private function eligible(Business $business, ?int $employeeId = null): Collection
    {
        if (! $business->isActive()) {
            return collect();
        }

        return $business->employees()->current()
            ->when($employeeId, fn ($q) => $q->whereKey($employeeId))
            ->whereHas('user', fn ($q) => $q->where('active', true)->where('role', User::ROLE_EMPLOYEE))
            ->with([
                'user', 'business',
                'documentRequests' => fn ($q) => $q->where('status', DocumentRequest::STATUS_AWAITING),
                'requests' => fn ($q) => $q->where('status', EmployeeRequest::PENDING),
            ])
            ->get();
    }

    private function visaRequestWaiting(Employee $employee): bool
    {
        return $employee->requests->contains(fn (EmployeeRequest $r) => $r->kind === 'change' && $r->change_kind === 'visa');
    }

    /** They have already uploaded it and HR has not decided yet. */
    private function uploadWaiting(Employee $employee, DocumentRequest $request): bool
    {
        return $employee->requests->contains(fn (EmployeeRequest $r) => $r->kind === 'document' && $r->document_request_id === $request->id);
    }

    private function item(string $type, string $subject, string $stage, CarbonInterface $date, string $title, string $text, string $href): array
    {
        return ['type' => $type, 'subject' => $subject, 'stage' => $stage.'@'.$date->format('Y-m-d'), 'date' => $date, 'title' => $title, 'text' => $text, 'href' => $href];
    }
}
