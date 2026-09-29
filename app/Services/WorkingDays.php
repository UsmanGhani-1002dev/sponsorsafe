<?php

namespace App\Services;

use App\Models\BankHoliday;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Working days = Monday to Friday, excluding England & Wales bank holidays.
 * Used for Home Office reporting deadlines, the unauthorised-absence streak and absence day counts.
 * Framework-free core (pure dates) so it is easy to test.
 */
class WorkingDays
{
    /** @var array<string, true> */
    private array $holidays = [];

    /** @param iterable<string|DateTimeInterface> $holidays */
    public function __construct(iterable $holidays = [])
    {
        foreach ($holidays as $h) {
            $this->holidays[$h instanceof DateTimeInterface ? $h->format('Y-m-d') : substr((string) $h, 0, 10)] = true;
        }
    }

    public static function fromDatabase(string $division = 'england-and-wales'): self
    {
        return new self(BankHoliday::query()->where('division', $division)->pluck('date')->map(fn ($d) => $d->format('Y-m-d')));
    }

    public function isWorkingDay(DateTimeInterface|string $date): bool
    {
        $d = self::day($date);
        $dow = (int) $d->format('N'); // 1 = Mon ... 7 = Sun

        return $dow <= 5 && ! isset($this->holidays[$d->format('Y-m-d')]);
    }

    /** Every working day from $start to $end inclusive, as Y-m-d strings. */
    public function between(DateTimeInterface|string $start, DateTimeInterface|string $end): array
    {
        $d = self::day($start);
        $end = self::day($end);
        $out = [];
        while ($d <= $end) {
            if ($this->isWorkingDay($d)) {
                $out[] = $d->format('Y-m-d');
            }
            $d = $d->modify('+1 day');
        }

        return $out;
    }

    public function count(DateTimeInterface|string $start, DateTimeInterface|string $end): int
    {
        return count($this->between($start, $end));
    }

    /** The date $n working days after $date (the start date itself is not counted). */
    public function add(DateTimeInterface|string $date, int $n): string
    {
        $d = self::day($date);
        $c = 0;
        while ($c < $n) {
            $d = $d->modify('+1 day');
            if ($this->isWorkingDay($d)) {
                $c++;
            }
        }

        return $d->format('Y-m-d');
    }

    /** The last working day before $date. */
    public function previous(DateTimeInterface|string $date): string
    {
        $d = self::day($date)->modify('-1 day');
        while (! $this->isWorkingDay($d)) {
            $d = $d->modify('-1 day');
        }

        return $d->format('Y-m-d');
    }

    /** Working days from $from (exclusive) to $to (inclusive); negative if $to is earlier. */
    public function until(DateTimeInterface|string $from, DateTimeInterface|string $to): int
    {
        $a = self::day($from)->format('Y-m-d');
        $b = self::day($to)->format('Y-m-d');
        if ($a === $b) {
            return 0;
        }
        if ($b < $a) {
            return -$this->until($b, $a);
        }

        return $this->count(self::day($a)->modify('+1 day'), $b);
    }

    private static function day(DateTimeInterface|string $date): DateTimeImmutable
    {
        return new DateTimeImmutable(($date instanceof DateTimeInterface ? $date->format('Y-m-d') : substr($date, 0, 10)).' 00:00:00');
    }
}
