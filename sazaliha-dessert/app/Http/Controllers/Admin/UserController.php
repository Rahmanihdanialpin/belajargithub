<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index()
    {
        $users = User::latest()->paginate(15);

        return view('admin.users.index', compact('users'));
    }

    public function edit(User $user)
    {
        $roles = ['admin' => 'Admin', 'super admin' => 'Super Admin'];

        return view('admin.users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'role' => ['nullable', 'string', 'in:admin,super admin'],
        ]);

        $newRole = $validated['role'] ?? null;

        if ($request->user()->id === $user->id && $user->hasRole('super admin') && $newRole !== 'super admin') {
            return back()->with('error', 'Anda tidak dapat menurunkan peran super admin diri sendiri.');
        }

        $user->is_admin = in_array($newRole, ['admin', 'super admin']);
        $user->save();

        if ($newRole) {
            $user->syncRoles([$newRole]);
        } else {
            $user->syncRoles([]);
        }

        return redirect()->route('admin.users.index')->with('success', 'Peran pengguna berhasil diperbarui.');
    }
}
