<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A message from the website contact form (or a lead from the AI chat, Stage 7c). */
class Enquiry extends Model
{
    public const NEW = 'new';
    public const HANDLED = 'handled';

    public const TOPICS = ['General question', 'Book a free demo', 'Corporate package', '1-to-1 training', 'Existing customer support'];

    protected $fillable = ['name', 'email', 'phone', 'topic', 'message', 'source', 'transcript', 'status', 'handled_by', 'handled_at', 'ip'];

    protected function casts(): array
    {
        return ['transcript' => 'array', 'handled_at' => 'datetime'];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(SuperAdmin::class, 'handled_by');
    }
}
