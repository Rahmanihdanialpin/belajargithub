<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AdminRolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $superAdmin = Role::firstOrCreate(['name' => 'super admin']);
        $admin = Role::firstOrCreate(['name' => 'admin']);

        $resources = ['ingredients', 'products', 'orders', 'reports'];
        foreach ($resources as $resource) {
            foreach (['create', 'read', 'update', 'delete'] as $action) {
                $permissionName = "{$resource}.{$action}";
                Permission::firstOrCreate(['name' => $permissionName]);
            }
        }

        $featureTogglePermission = Permission::firstOrCreate([
            'name' => 'admin.feature-toggle.manage',
        ]);

        // Super admin can do everything
        $allPermissions = Permission::query()->get();
        $superAdmin->syncPermissions($allPermissions);
        $superAdmin->givePermissionTo($featureTogglePermission);

        // Admin default: read only
        $adminPermissions = Permission::query()->whereIn('name', [
            'ingredients.read',
            'products.read',
            'orders.read',
            'reports.read',
        ])->get();
        $admin->syncPermissions($adminPermissions);

        // Optional: ensure admin_feature_permissions rows exist
        foreach ($resources as $resource) {
            \App\Models\AdminFeaturePermission::firstOrCreate([
                'resource' => $resource,
            ], [
                'can_create' => false,
                'can_read' => true,
                'can_update' => false,
                'can_delete' => false,
            ]);
        }
    }
}
