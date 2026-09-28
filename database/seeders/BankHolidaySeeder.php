<?php

namespace Database\Seeders;

use App\Models\BankHoliday;
use Illuminate\Database\Seeder;

/** England & Wales bank holidays 2025–2027 so the app works before the first gov.uk sync. */
class BankHolidaySeeder extends Seeder
{
    public function run(): void
    {
        $days = [
            '2025-01-01' => "New Year's Day", '2025-04-18' => 'Good Friday', '2025-04-21' => 'Easter Monday',
            '2025-05-05' => 'Early May bank holiday', '2025-05-26' => 'Spring bank holiday', '2025-08-25' => 'Summer bank holiday',
            '2025-12-25' => 'Christmas Day', '2025-12-26' => 'Boxing Day',
            '2026-01-01' => "New Year's Day", '2026-04-03' => 'Good Friday', '2026-04-06' => 'Easter Monday',
            '2026-05-04' => 'Early May bank holiday', '2026-05-25' => 'Spring bank holiday', '2026-08-31' => 'Summer bank holiday',
            '2026-12-25' => 'Christmas Day', '2026-12-28' => 'Boxing Day (substitute day)',
            '2027-01-01' => "New Year's Day", '2027-03-26' => 'Good Friday', '2027-03-29' => 'Easter Monday',
            '2027-05-03' => 'Early May bank holiday', '2027-05-31' => 'Spring bank holiday', '2027-08-30' => 'Summer bank holiday',
            '2027-12-27' => 'Christmas Day (substitute day)', '2027-12-28' => 'Boxing Day (substitute day)',
        ];
        foreach ($days as $date => $title) {
            BankHoliday::updateOrCreate(['date' => $date, 'division' => 'england-and-wales'], ['title' => $title]);
        }
    }
}
