<?php

namespace App\Models;

use App\Support\Pricing;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Cashier\Billable;

class Business extends Model
{
    use Billable, HasFactory;

    public const ACTIVE = 'active';
    public const SUSPENDED = 'suspended';
    /** Signed up on the website but has not finished paying yet; nobody can sign in. */
    public const PENDING = 'pending';

    public const SUSPENDED_MANUAL = 'manual';
    public const SUSPENDED_PAYMENT = 'payment';
    public const SUSPENDED_CANCELLED = 'cancelled';

    protected $fillable = [
        'name', 'licence_number', 'authorising_officer', 'phone', 'registered_address', 'status', 'plan', 'plan_price_pence', 'employee_limit', 'employees_band',
        'payment_provider', 'payment_label', 'next_payment_on', 'payment_failed_on', 'grace_ends_on', 'settings', 'suspended_at', 'suspended_reason',
        'paypal_subscription_id', 'paypal_plan_id', 'price_change_pence', 'price_change_limit', 'price_change_plan', 'price_change_on',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'next_payment_on' => 'date',
            'payment_failed_on' => 'date',
            'grace_ends_on' => 'date',
            'price_change_on' => 'date',
            'suspended_at' => 'datetime',
            'trial_ends_at' => 'datetime',
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

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function reportTasks(): HasMany
    {
        return $this->hasMany(ReportTask::class);
    }

    public function keyPersonnel(): HasMany
    {
        return $this->hasMany(KeyPerson::class);
    }

    public function workSites(): HasMany
    {
        return $this->hasMany(WorkSite::class);
    }

    /** Current employees count towards the plan limit; leavers do not. */
    public function employeeLimitReached(): bool
    {
        return $this->employees()->current()->count() >= $this->employee_limit;
    }

    public function currentEmployeeCount(): int
    {
        return $this->employees()->current()->count();
    }

    public function planName(): string
    {
        return Pricing::name($this->plan);
    }

    /** The smallest self-service plan with room for more employees than now, if any (never for Corporate). */
    public function nextTier(): ?string
    {
        if ($this->plan === Pricing::CORPORATE) {
            return null;
        }
        foreach (Pricing::current()['tiers'] as $key => $tier) {
            if ($tier['employee_limit'] > $this->employee_limit) {
                return $key;
            }
        }

        return null;
    }

    /** What to say when the employee limit is reached: upgrade in Settings, or ask about Corporate. */
    public function limitMessage(): string
    {
        $covers = "Your plan covers up to {$this->employee_limit} employees.";
        if ($next = $this->nextTier()) {
            $tier = Pricing::tier($next);

            return "{$covers} Upgrade to ".Pricing::TIERS[$next].' (£'.Pricing::pounds($tier['price_pence'])." a month, up to {$tier['employee_limit']}) in Settings to add more.";
        }

        return "{$covers} Contact us about a Corporate package to add more.";
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function isPending(): bool
    {
        return $this->status === self::PENDING;
    }

    /** A failed payment is waiting to be fixed; access continues until grace_ends_on. */
    public function inGrace(): bool
    {
        return $this->isActive() && $this->grace_ends_on !== null;
    }

    /** A compliance rule value for this business, falling back to the platform default. */
    public function rule(string $key): mixed
    {
        return data_get($this->settings, $key, config("sponsorsafe.rules.$key"));
    }

    /** Stripe: the customer is the business, billed to its first admin's email. */
    public function stripeName(): ?string
    {
        return $this->name;
    }

    public function stripeEmail(): ?string
    {
        return $this->admins()->orderBy('id')->value('email');
    }

    public function stripePhone(): ?string
    {
        return $this->phone;
    }
}
