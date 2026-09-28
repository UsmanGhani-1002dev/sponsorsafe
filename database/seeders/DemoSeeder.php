<?php

namespace Database\Seeders;

use App\Models\Business;
use App\Models\SuperAdmin;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Demo data matching the agreed prototype. Every demo password is "password". Never run in production. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $businesses = [
            ['Demo Retail Ltd', 'active', 'Card ending 4242', 'stripe', '2026-10-10', ['Nadia Khan', 'hr@demo-retail.example'],
                ['Aisha Rahman', 'Rahul Mehta', 'James Carter', 'Kasia Nowak', 'Daniel Okafor'], 'demo-retail.example'],
            ['Demo Catering Ltd', 'active', 'PayPal', 'paypal', '2026-10-02', ['Mark Evans', 'hr@demo-catering.example'],
                ['Fatima Hussain', 'Tom Richards'], 'demo-catering.example'],
            ['Demo Cafe Ltd', 'suspended', 'Card ending 1881', 'stripe', null, ['Leo Grant', 'hr@demo-cafe.example'],
                ['Chloe Martin', 'Omar Siddiqui', 'Beth Walker'], 'demo-cafe.example'],
        ];

        foreach ($businesses as [$name, $status, $label, $provider, $next, [$adminName, $adminEmail], $staff, $domain]) {
            $b = Business::updateOrCreate(['name' => $name], [
                'licence_number' => '[Licence number]', 'authorising_officer' => '[Authorising Officer]',
                'status' => $status, 'payment_label' => $label, 'payment_provider' => $provider, 'next_payment_on' => $next,
                'suspended_at' => $status === 'suspended' ? now() : null,
            ]);
            User::updateOrCreate(['email' => $adminEmail], ['business_id' => $b->id, 'name' => $adminName, 'role' => User::ROLE_ADMIN, 'password' => 'password']);
            foreach ($staff as $person) {
                $email = strtolower(str_replace(' ', '.', $person)).'@'.$domain;
                User::updateOrCreate(['email' => $email], ['business_id' => $b->id, 'name' => $person, 'role' => User::ROLE_EMPLOYEE, 'password' => 'password']);
            }
        }

        SuperAdmin::updateOrCreate(['email' => 'owner@sponsorsafe.example'], ['name' => 'Platform owner', 'password' => 'password']);
    }
}
