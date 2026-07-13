<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\DB;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->get();
        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:roles,name',
            'permissions' => 'array'
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);
            
            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }
            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => ['status' => 'success', 'description' => 'Role created successfully.'],
                'data' => $role->load('permissions')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => ['status' => 'error', 'description' => $e->getMessage()]
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);
        
        $request->validate([
            'name' => 'required|unique:roles,name,' . $role->id,
            'permissions' => 'array'
        ]);

        DB::beginTransaction();
        try {
            $role->update(['name' => $request->name]);
            
            if ($request->has('permissions')) {
                $role->syncPermissions($request->permissions);
            }
            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => ['status' => 'success', 'description' => 'Role updated successfully.'],
                'data' => $role->load('permissions')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => ['status' => 'error', 'description' => $e->getMessage()]
            ], 500);
        }
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        
        if ($role->name === 'admin') {
            return response()->json([
                'success' => false,
                'message' => ['status' => 'error', 'description' => 'Cannot delete admin role.']
            ], 403);
        }

        $role->delete();
        return response()->json([
            'success' => true,
            'message' => ['status' => 'success', 'description' => 'Role deleted successfully.']
        ]);
    }

    public function getPermissions()
    {
        $permissions = Permission::all();
        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }
}
