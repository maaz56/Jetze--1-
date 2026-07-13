<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use App\Models\User;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Reset cached roles and permissions
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // Create permissions
        $permissions = [
            'view-dashboard',
            'view-bookings',
            'manage-bookings',
            'manage-cms',
            'manage-finance',
            'manage-settings',
            'view-ledger',
            'manage-marketing',
            'manage-airlines',
            'manage-airports',
            'manage-staff',
            'manage-roles',
            'manage-customers',
            'view-activity-logs',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Create roles and assign existing permissions
        $adminRole = Role::findOrCreate('admin', 'web');
        $adminRole->givePermissionTo(Permission::all());

        // Assign admin role to existing admin users if any
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $admin->assignRole($adminRole);
        }
    }
}
