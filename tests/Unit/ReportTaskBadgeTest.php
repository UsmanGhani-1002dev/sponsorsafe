<?php

namespace Tests\Unit;

use App\Models\ReportTask;
use App\Services\WorkingDays;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** compliance-rules §4 status badges. */
class ReportTaskBadgeTest extends TestCase
{
    private function badge(string $deadline, string $status = ReportTask::PENDING): array
    {
        $task = (new ReportTask)->forceFill(['status' => $status, 'deadline' => $deadline]);

        return $task->badge(new WorkingDays(['2026-08-31']), Carbon::parse('2026-09-28'));
    }

    public function test_badges_follow_the_deadline_in_working_days(): void
    {
        $this->assertSame(['text' => 'Overdue by 2 working days', 'tone' => 'red'], $this->badge('2026-09-24'));
        $this->assertSame(['text' => 'Overdue by 1 working day', 'tone' => 'red'], $this->badge('2026-09-25'));
        $this->assertSame(['text' => 'Due today', 'tone' => 'red'], $this->badge('2026-09-28'));
        $this->assertSame(['text' => 'Due in 1 working day', 'tone' => 'amber'], $this->badge('2026-09-29'));
        $this->assertSame(['text' => 'Due in 5 working days', 'tone' => 'amber'], $this->badge('2026-10-05'));
        $this->assertSame(['text' => '6 working days left', 'tone' => 'blue'], $this->badge('2026-10-06'));
    }

    public function test_completed_tasks(): void
    {
        $this->assertSame(['text' => 'Reported to Home Office', 'tone' => 'green'], $this->badge('2026-09-01', ReportTask::REPORTED));
        $this->assertSame(['text' => 'Not required', 'tone' => 'grey'], $this->badge('2026-09-01', ReportTask::NOT_REQUIRED));
    }
}
