<?php

namespace Teleurban\SwiftAuth\Controllers;

use Illuminate\Support\Facades\Validator;
use Teleurban\SwiftAuth\Models\Role;
use Teleurban\SwiftAuth\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;


class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::all();

        return $this->render('swift-auth::user.role.index', 'User/Role/Index', ['roles' => $roles]);
    }

    public function create()
    {
        return view('swift-auth::user.role.create');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        Role::create($request->only('name', 'description'));

        return redirect()->route('swift-auth.user.role.index')->with('success', 'Role created successfully.');
    }

    public function edit($id)
    {
        $role = Role::findOrFail($id);

        return view('swift-auth::user.role.edit')->with('role', $role);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name,' . $role->id,
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $role->update($request->only('name', 'description'));

        return redirect()->route('swift-auth.user.role.index')->with('success', 'Role updated successfully.');
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);
        $role->delete();

        return redirect()->route('swift-auth.user.role.index')->with('success', 'Role deleted successfully.');
    }

    public function assignUserForm()
    {
        $users = User::all();
        $roles = Role::all();

        return view('swift-auth::user.role.assign')->with('users', $users)->with('roles', $roles);
    }

    public function assignUser(Request $request)
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
