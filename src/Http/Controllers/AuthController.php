<?php

namespace Teleurban\SwiftAuth\Http\Controllers;

use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Teleurban\SwiftAuth\Models\User;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Config;

class AuthController extends Controller
{
    protected function render($bladeView, $inertiaComponent, $data = [])
    {
        // Pasar mensajes de flash a la vista
        $flashMessages = [
            'success' => session('success'),
            'error' => session('error'),
            'status' => session('status'),
        ];

        $data = array_merge($data, $flashMessages);

        return Config::get('swift-auth.frontend') === 'blade'
            ? view($bladeView, $data)
            : Inertia::render($inertiaComponent, $data);
    }

    public function showLoginForm()
    {
        return $this->render('swift-auth::login', 'Login');
    }

    public function showRegisterForm()
    {
        return $this->render('swift-auth::register', 'Register');
    }

    public function showResetForm()
    {
        return $this->render('swift-auth::password.email', 'ForgotPassword');
    }

    public function showNewPasswordForm()
    {
        return $this->render('swift-auth::password.reset', 'ResetPassword');
    }

    public function showNewUserForm()
    {
        return $this->render('swift-auth::user.create', 'User/Create');
    }

    public function showEditUserForm($id)
    {
        $user = User::findOrFail($id);
        return $this->render('swift-auth::user.edit', 'User/Edit', ['user' => $user]);
    }

    public function index()
    {
        $users = User::all();
        return $this->render('swift-auth::user.index', 'User/Index', ['users' => $users]);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return $this->render('swift-auth::user.show', 'User/Show', ['user' => $user]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        if (!$request->startSession) {
            Auth::login($user);

            return redirect()->to(Config::get('swift-auth.success_url'))->with('success', 'Registration successful.');
        }

        return redirect()->route('swift-auth.user.index')->with('success', 'Registration successful.');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            return redirect()->to(Config::get('swift-auth.success_url'))->with('success', 'Login successful.');
        }

        return back()->with('error', 'Invalid credentials.');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('swift-auth.login')->with('success', 'Logged out successfully.');
    }

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => 'required|email|exists:users,email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? back()->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
            'password' => 'required|min:6|confirmed',
            'token' => 'required',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill(['password' => Hash::make($password)])->save();
            }
        );

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('swift-auth.login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)]);
    }

    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user->update([
            'name' => $request->name ?? $user->name,
            'email' => $request->email ?? $user->email,
        ]);

        return redirect()->route('swift-auth.user.index')->with('success', 'User updated successfully.');
    }

    public function destroy($id)
    {
        if (Auth::id() === (int) $id) {
            return back()->with('error', 'You cannot delete your own account.');
        }

        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('swift-auth.user.index')->with('success', 'User successfully deleted.');
    }
}
