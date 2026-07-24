<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Database\Seeders\AdminRolesAndPermissionsSeeder;

class TestUsersSeeder extends Seeder
{
    public function run(): void
    {
        (new AdminRolesAndPermissionsSeeder())->run();

        $password = 'password';

        User::updateOrCreate(
            ['email' => 'customer@test.com'],
            [
                'name' => 'Customer Sazaliha',
                'password' => $password,
                'is_admin' => false,
                'email_verified_at' => now(),
            ]
        );

        $adminRole = Role::firstOrCreate(['name' => 'admin']);

        $admin = User::updateOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Admin Sazaliha',
                'password' => $password,
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles([$adminRole->name]);

        $adminTest = User::updateOrCreate(
            ['email' => 'admin@sazaliha.test'],
            [
                'name' => 'Admin Sazaliha',
                'password' => $password,
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );
        $adminTest->syncRoles([$adminRole->name]);

        $superAdminRole = Role::firstOrCreate(['name' => 'super admin']);

        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@test.com'],
            [
                'name' => 'Super Admin Sazaliha',
                'password' => $password,
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        $superAdmin->syncRoles([$superAdminRole->name]);
    }
}
