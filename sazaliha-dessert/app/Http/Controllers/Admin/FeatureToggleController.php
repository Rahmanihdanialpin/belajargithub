<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminFeaturePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class FeatureToggleController extends Controller
{
    public function index()
    {
        $resources = ['ingredients', 'products', 'orders', 'reports'];

        $rows = AdminFeaturePermission::query()->whereIn('resource', $resources)->get()->keyBy('resource');

        return view('admin.feature-toggles.index', [
            'rows' => $rows,
            'resources' => $resources,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'resources' => 'required|array',
            'resources.*' => 'required|array',
            'resources.*.can_create' => 'boolean',
            'resources.*.can_read' => 'boolean',
            'resources.*.can_update' => 'boolean',
            'resources.*.can_delete' => 'boolean',
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['resources'] as $resource => $flags) {
                $row = AdminFeaturePermission::updateOrCreate(
                    ['resource' => $resource],
                    [
                        'can_create' => $flags['can_create'] ?? false,
                        'can_read' => $flags['can_read'] ?? false,
                        'can_update' => $flags['can_update'] ?? false,
                        'can_delete' => $flags['can_delete'] ?? false,
                    ]
                );

                $this->syncPermissionForResource($resource, $row);
            }
        });

        return back()->with('status', 'Berhasil update fitur admin.');
    }

    private function syncPermissionForResource(string $resource, AdminFeaturePermission $row): void
    {
        $map = [
            'create' => $row->can_create,
            'read' => $row->can_read,
            'update' => $row->can_update,
            'delete' => $row->can_delete,
        ];

        $roleAdmin = Role::where('name', 'admin')->first();
        if (!$roleAdmin) {
            return;
        }

        foreach ($map as $action => $allowed) {
            $permissionName = "{$resource}.{$action}";
            $permission = Permission::firstOrCreate(['name' => $permissionName]);

            if ($allowed) {
                $roleAdmin->givePermissionTo($permission);
            } else {
                $roleAdmin->revokePermissionTo($permission);
            }
        }
    }
}

