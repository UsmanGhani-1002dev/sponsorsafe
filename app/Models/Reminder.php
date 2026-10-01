<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One reminder stage already emailed (see App\Services\Reminders). */
class Reminder extends Model
{
    public $timestamps = false;

    protected $fillable = ['business_id', 'type', 'subject', 'stage', 'sent_at'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
