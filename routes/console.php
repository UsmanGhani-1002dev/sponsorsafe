<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('bank-holidays:sync')->monthly();
