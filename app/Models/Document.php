<?php

namespace App\Models;

use App\Enums\DocumentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A private file on the employee's record. Served only through DocumentController (audited). */
class Document extends Model
{
    protected $fillable = ['business_id', 'employee_id', 'category', 'original_name', 'path', 'mime', 'size', 'expires_on', 'uploaded_by', 'uploaded_via'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['category' => DocumentCategory::class, 'expires_on' => 'date'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** "1.2 MB", "340 KB" */
    public function humanSize(): string
    {
        return $this->size >= 1048576 ? number_format($this->size / 1048576, 1).' MB' : max(1, (int) round($this->size / 1024)).' KB';
    }
}
