<?php

namespace App\Console\Commands;

use App\Models\BankHoliday;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SyncBankHolidays extends Command
{
    protected $signature = 'bank-holidays:sync';

    protected $description = 'Import UK bank holidays from gov.uk (run yearly; scheduled monthly).';

    public function handle(): int
    {
        $res = Http::timeout(20)->get('https://www.gov.uk/bank-holidays.json');
        if (! $res->ok()) {
            $this->error('Could not reach gov.uk ('.$res->status().').');

            return self::FAILURE;
        }
        $n = 0;
        foreach ($res->json() as $division => $data) {
            foreach ($data['events'] ?? [] as $e) {
                BankHoliday::updateOrCreate(['date' => $e['date'], 'division' => $division], ['title' => $e['title']]);
                $n++;
            }
        }
        $this->info("Imported {$n} bank holidays.");

        return self::SUCCESS;
    }
}
