<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    // ==========================
    // Display Users
    // ==========================
    public function index(Request $request)
    {
        $role = $request->role;
        $search = $request->search;
        $status = $request->status;

        $users = User::query();

        // Filter by role
        if ($role) {
            $users->where('role', $role);
        }

        // Search
        if ($search) {
            $users->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('username', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status) {
            $users->where('status', $status);
        }

        $users = $users->latest()->paginate(10);

        return view('owner.users', compact(
            'users',
            'role',
            'search',
            'status'
        ));
    }

    // ==========================
    // Store User
    // ==========================
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|max:255',
            'username' => 'required|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|in:owner,manager,staff',
        ]);

        User::create([
            'name' => $request->name,
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'status' => 'active',
        ]);

        return redirect()
            ->route('owner.users')
            ->with('success', 'User added successfully.');
    }

    // ==========================
    // Update User
    // ==========================
    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|max:255',
            'username' => 'required|unique:users,username,' . $user->id,
            'email' => 'required|email|unique:users,email,' . $user->id,
            'role' => 'required|in:owner,manager,staff',
        ]);

        $user->name = $request->name;
        $user->username = $request->username;
        $user->email = $request->email;
        $user->role = $request->role;

        if (!empty($request->password)) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        return redirect()
            ->route('owner.users')
            ->with('success', 'User updated successfully.');
    }

    // ==========================
    // Activate / Deactivate
    // ==========================
    public function toggleStatus(User $user)
    {
        $user->status = $user->status === 'active'
            ? 'inactive'
            : 'active';

        $user->save();

        return redirect()
            ->route('owner.users')
            ->with('success', 'User status updated.');
    }
}