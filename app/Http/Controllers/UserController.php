<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('roles')->get();

        $roles = Role::all();
        return view('users.index', compact('users', 'roles'));
    }

    public function create()
    {
        $roles = Role::all();
        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        // Validate request
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username',
            'password' => 'required|min:6|confirmed',
            'role' => 'required|exists:roles,id',
        ]);

        // Prepare data
        $data = $request->only(['name', 'username']);
        $data['password'] = Hash::make($request->password);

        // Create user
        $user = User::create($data);

        // Assign role
        $role = Role::findById($request->role);
        $user->assignRole($role);

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function update(Request $request, $id)
    {
        // Validate the request
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|unique:users,username,' . $id,
            'role' => 'required|exists:roles,id',
        ]);

        // Find the user
        $user = User::findOrFail($id);

        // Get basic user data
        $data = $request->only(['name', 'username', 'signature']);

        try {
            // Update user data
            $user->update($data);
            \Log::info('User data updated:', ['user_id' => $user->id, 'data' => $data]);

            // Update user role
            $role = Role::findById($request->role);
            if ($role) {
                $user->syncRoles([$role->name]);
                \Log::info('User role updated:', [
                    'user_id' => $user->id,
                    'role' => $role->name
                ]);
            } else {
                \Log::error('Role not found:', ['role_id' => $request->role]);
                return redirect()->back()
                    ->with('error', 'Selected role not found.')
                    ->withInput();
            }

            return redirect()->route('users.index')
                ->with('success', 'User updated successfully.');

        } catch (\Exception $e) {
            \Log::error('User update error: ' . $e->getMessage(), [
                'user_id' => $user->id ?? $id,
                'trace' => $e->getTraceAsString()
            ]);

            return redirect()->back()
                ->with('error', 'Error updating user: ' . $e->getMessage())
                ->withInput();
        }
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        return view('users.edit', compact('user', 'roles'));
    }

    public function show($id)
    {
        $user = User::with('roles:id,name')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => [
                'id'       => $user->id,
                'name'     => $user->name,
                'username' => $user->username,
                'roles'    => $user->roles->map(fn($r) => [
                'id'   => $r->id,
                'name' => $r->name
                ])
            ]
        ]);
    }

    public function changePassword(Request $request, $id)
    {
        $request->validate([
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::findOrFail($id);

        // Prevent changing password of super admin if needed
        if ($user->id == 1 || $user->hasRole('super-admin')) {
            return redirect()->back()->with('error', 'Cannot change password of the super admin user.');
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return redirect()->route('users.index')->with('success', 'Password changed successfully.');
    }

    public function toggleActive($id)
    {
        $user = User::findOrFail($id);

        // Prevent super admin from being deactivated (assuming ID=1 or role 'super-admin')
        if ($user->id == 1 || $user->hasRole('super-admin')) {
            return redirect()->back()->with('error', 'Cannot deactivate the super admin user.');
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $status = $user->is_active ? 'activated' : 'deactivated';

        return redirect()->back()->with('success', "User {$status} successfully.");
    }

    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting super admin by ID or role
        if ($user->id == 1 || $user->hasRole('superadmin')) {
            return redirect()->back()->with('error', 'Cannot delete the superadmin user.');
        }

        $user->delete();

        return redirect()->back()->with('success', 'User deleted successfully.');
    }
}
