<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bank-holidays:sync')->monthly();
Schedule::command('billing:check')->dailyAt('06:00');
