<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $role = $request->role;
        $search = $request->search;
        $status = $request->status;

        $users = User::query();

        if ($role) {
            $users->where('role', $role);
        }

        if ($search) {
            $users->where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        if ($status) {
            $users->where('status', $status);
        }

        $users = $users->latest()->paginate(5)->withQueryString();

        return view('owner.users', compact(
            'users',
            'role',
            'search',
            'status'
        ));
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|max:255',
            'username' => 'required|unique:users',
            'email' => 'required|email|unique:users',
            'password' => ['required', Password::defaults()],
            'security_question' => 'required|string|max:255',
            'security_answer' => 'required|string|min:8|max:255',
            'role' => 'required|in:owner,manager,staff',
        ]);

        if ($validator->fails()) {
            return $this->backWithErrors($request, $validator);
        }

        $validated = $validator->validated();

        $user = new User;
        $user->forceFill([
            'name' => $validated['name'],
            'username' => $validated['username'],
            'email' => $validated['email'],
            'password' => $validated['password'],
            'security_question' => $validated['security_question'],
            'security_answer' => User::hashSecurityAnswer($validated['security_answer']),
            'role' => $validated['role'],
            'status' => 'active',
        ])->save();

        return redirect()
            ->route('owner.users')
            ->with('success', 'User added successfully.');
    }

    public function update(Request $request, User $user)
    {
        $rules = [
            'name' => 'required|max:255',
            'username' => 'required|unique:users,username,'.$user->id,
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => 'required|in:owner,manager,staff',
            'security_question' => 'nullable|string|max:255',
            'security_answer' => 'nullable|string|max:255',
        ];

        if ($request->filled('password')) {
            $rules['password'] = ['required', Password::defaults()];
        }

        if ($request->filled('security_answer') || ($request->filled('security_question') && ! $user->hasSecurityAnswer())) {
            $rules['security_question'] = 'required|string|max:255';
            $rules['security_answer'] = 'required|string|min:8|max:255';
        }

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return $this->backWithErrors($request, $validator);
        }

        $validated = $validator->validated();

        if ($blocked = $this->blockedOwnerChange($user, $validated['role'], null)) {
            return $blocked;
        }

        $user->name = $validated['name'];
        $user->username = $validated['username'];
        $user->email = $validated['email'];
        $user->role = $validated['role'];

        if ($request->filled('security_question')) {
            $user->security_question = $validated['security_question'];
        }

        if ($request->filled('security_answer')) {
            $user->security_answer = User::hashSecurityAnswer($validated['security_answer']);
        }

        if ($request->filled('password')) {
            $user->password = $validated['password'];
            $user->rotateAccess(auth()->id() === $user->id ? session()->getId() : null);
        } else {
            $user->save();
        }

        return redirect()
            ->route('owner.users')
            ->with('success', 'User updated successfully.');
    }

    public function toggleStatus(User $user)
    {
        $nextStatus = $user->status === 'active' ? 'inactive' : 'active';

        if ($blocked = $this->blockedOwnerChange($user, null, $nextStatus)) {
            return $blocked;
        }

        $user->status = $nextStatus;

        if ($nextStatus === 'inactive') {
            $user->rotateAccess();
        } else {
            $user->save();
        }

        return redirect()
            ->route('owner.users')
            ->with('success', 'User status updated.');
    }

    private function blockedOwnerChange(User $user, ?string $newRole, ?string $newStatus)
    {
        $actor = auth()->user();
        $isSelf = $actor && (int) $actor->id === (int) $user->id;

        if ($isSelf && $newStatus !== null && $newStatus !== 'active') {
            return back()->with('error', 'You cannot change your own account status.');
        }

        if ($isSelf && $newRole !== null && $newRole !== 'owner' && $user->isOwner()) {
            return back()->with('error', 'You cannot change your own role.');
        }

        $removesLastOwner = $user->isOwner() && (
            ($newStatus !== null && $newStatus !== 'active')
            || ($newRole !== null && $newRole !== 'owner')
        );

        if ($removesLastOwner && $this->otherActiveOwnerCount($user) < 1) {
            return back()->with('error', 'The last active farm owner must stay an owner.');
        }

        return null;
    }

    private function otherActiveOwnerCount(User $user): int
    {
        return User::query()
            ->where('id', '!=', $user->id)
            ->where('status', 'active')
            ->get()
            ->filter(fn (User $account) => $account->isOwner())
            ->count();
    }

    private function backWithErrors(Request $request, \Illuminate\Contracts\Validation\Validator $validator)
    {
        return back()
            ->withErrors($validator)
            ->withInput($request->except(['password', 'security_answer']));
    }
}
