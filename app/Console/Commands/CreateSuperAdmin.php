<?php

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateSuperAdmin extends Command
{
    protected $signature = 'ops:create-admin {email} {--name=Super admin}';

    protected $description = 'Create a super admin. The authenticator app is set up on first sign-in.';

    public function handle(): int
    {
        $password = Str::password(20, symbols: false);
        SuperAdmin::updateOrCreate(
            ['email' => mb_strtolower($this->argument('email'))],
            ['name' => $this->option('name'), 'password' => $password, 'two_factor_secret' => null, 'two_factor_confirmed_at' => null],
        );
        $this->info('Super admin ready.');
        $this->line('Sign in at: '.url(config('sponsorsafe.ops_path').'/login'));
        $this->line('Email:      '.$this->argument('email'));
        $this->line('Password:   '.$password);
        $this->warn('Store this password safely. You will scan a QR code for your authenticator app on first sign-in.');

        return self::SUCCESS;
    }
}
