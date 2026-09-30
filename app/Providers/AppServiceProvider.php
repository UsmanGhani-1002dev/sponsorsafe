<?php

namespace App\Providers;

use App\Models\Absence;
use App\Models\Employee;
use App\Models\EmployeeChange;
use App\Models\KeyPerson;
use App\Models\WorkSite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Catch N+1 queries while developing and testing: a lazy load in a loop throws.
        Model::preventLazyLoading(! $this->app->isProduction());

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
