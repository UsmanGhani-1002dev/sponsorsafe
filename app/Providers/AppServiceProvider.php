<?php

namespace App\Providers;

use App\Models\Absence;
use App\Models\Business;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\KeyPerson;
use App\Models\WorkSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Our own webhook route (keys from the super admin, signature always required).
        Cashier::ignoreRoutes();
    }

    public function boot(): void
    {
        // Catch N+1 queries while developing and testing: a lazy load in a loop throws.
        Model::preventLazyLoading(! $this->app->isProduction());

        // The business pays, not a user.
        Cashier::useCustomerModel(Business::class);

        // Short, stable names for records linked to Home Office tasks (report_tasks.subject_type).
        Relation::morphMap([
            'absence' => Absence::class,
            'employee' => Employee::class,
            'employee_change' => EmployeeChange::class,
            'key_person' => KeyPerson::class,
            'work_site' => WorkSite::class,
        ]);
    }
}
