<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Writes one audit-log row for every sensitive action (who, what, when, from where). */
class Audit
{
    public static function log(string $action, ?Model $subject = null, array $meta = [], User|SuperAdmin|null $actor = null, ?int $businessId = null): void
    {
        $actor ??= auth('ops')->user() ?? auth('web')->user();

        AuditLog::create([
            'business_id' => $businessId ?? ($actor instanceof User ? $actor->business_id : ($subject?->business_id ?? null)),
            'actor_type' => match (true) {
                $actor instanceof SuperAdmin => 'super_admin',
                $actor instanceof User => 'user',
                default => 'system',
            },
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? class_basename($subject) : null,
            'subject_id' => $subject?->getKey(),
            'meta' => $meta ?: null,
            'ip' => request()?->ip(),
        ]);
    }
}
