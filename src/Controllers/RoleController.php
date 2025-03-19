<?php

namespace Teleurban\SwiftAuth\Controllers;

use Teleurban\SwiftAuth\Models\Role;
use Teleurban\SwiftAuth\Models\User;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::all();

        return view('swift-auth::user.roles.index', compact('roles'));
    }

    public function create()
    {
        return view('swift-auth::user.roles.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'description' => 'nullable|string',
        ]);

        Role::create($request->only('name', 'description'));

        return redirect()->route('swift-auth.user.role.index')->with('success', 'Role created successfully.');
    }

    public function edit(Role $role)
    {
        return view('swift-auth::user.roles.edit', compact('role'));
    }

    public function update(Request $request, Role $role)
    {
        $request->validate([
            'name' => 'required|string|unique:roles,name,' . $role->id,
            'description' => 'nullable|string',
        ]);

        $role->update($request->only('name', 'description'));

        return redirect()->route('swift-auth.user.role.index')->with('success', 'Role updated successfully.');
    }

    public function destroy(Role $role)
    {
        $role->delete();

        return redirect()->route('swift-auth.user.role.index')->with('success', 'Role deleted successfully.');
    }

    public function assignRoleToUser(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::findOrFail($request->user_id);
        $user->roles()->syncWithoutDetaching([$request->role_id]);

        return redirect()->back()->with('success', 'Role assigned successfully.');
    }
}
