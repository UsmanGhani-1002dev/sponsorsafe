<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankHoliday extends Model
{
    public $timestamps = false;

    protected $fillable = ['date', 'title', 'division'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }
}
