<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'licence_number', 'authorising_officer', 'status', 'plan_price_pence', 'employee_limit',
        'payment_provider', 'payment_label', 'next_payment_on', 'settings', 'suspended_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'next_payment_on' => 'date',
            'suspended_at' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function admins(): HasMany
    {
        return $this->users()->where('role', User::ROLE_ADMIN);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** A compliance rule value for this business, falling back to the platform default. */
    public function rule(string $key): mixed
    {
        return data_get($this->settings, $key, config("sponsorsafe.rules.$key"));
    }
}
