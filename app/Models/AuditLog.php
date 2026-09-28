<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['business_id', 'actor_type', 'actor_id', 'action', 'subject_type', 'subject_id', 'meta', 'ip'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }
}
