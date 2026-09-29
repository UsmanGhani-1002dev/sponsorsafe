<?php

namespace App\Services;

/** The Home Office check for one absence, shown live on "Record absence" and stored when it is saved. */
final class AbsenceCheck
{
    public const INVALID = 'invalid';
    public const NONE = 'none';         // no report needed
    public const NOT_YET = 'not_yet';   // counting towards a threshold, no report yet
    public const REPORT = 'report';     // report to the Home Office by $deadline

    /** @param list<string> $warnings */
    public function __construct(
        public readonly string $status,
        public readonly string $title,
        public readonly string $detail,
        public readonly int $days = 0,
        public readonly ?string $trigger = null,
        public readonly ?string $deadline = null,
        public readonly ?string $event = null,
        public readonly array $warnings = [],
    ) {}

    public function reportable(): bool
    {
        return $this->status === self::REPORT;
    }

    public function valid(): bool
    {
        return $this->status !== self::INVALID;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status, 'title' => $this->title, 'detail' => $this->detail, 'days' => $this->days,
            'trigger' => $this->trigger, 'deadline' => $this->deadline, 'event' => $this->event, 'warnings' => $this->warnings,
        ];
    }
}
