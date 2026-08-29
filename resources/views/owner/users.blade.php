@extends('layouts.sidebar')

@section('title', 'Users')
@section('page-label', 'Users')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/users.css') }}">
@endpush

@section('content')
<div class="users-page">

    <div class="page-header">
        <h1>User Management</h1>
        <button class="btn-primary" onclick="openAddModal()">+ Add User</button>
    </div>

    <!-- ROLE FILTER PILLS -->
    <div class="role-pills">
        <a href="{{ route('owner.users') }}" class="pill {{ !$role ? 'active' : '' }}">All Roles</a>
        <a href="{{ route('owner.users', ['role' => 'owner']) }}" class="pill {{ $role === 'owner' ? 'active' : '' }}">Farm Owner</a>
        <a href="{{ route('owner.users', ['role' => 'manager']) }}" class="pill {{ $role === 'manager' ? 'active' : '' }}">Farm Manager</a>
        <a href="{{ route('owner.users', ['role' => 'staff']) }}" class="pill {{ $role === 'staff' ? 'active' : '' }}">Farm Worker</a>
    </div>

    <!-- SEARCH + STATUS -->
    <form method="GET" class="search-bar">
        <input type="hidden" name="role" value="{{ $role }}">
        <input type="text" name="search" placeholder="Search name or username..." value="{{ $search }}" onchange="this.form.submit()">

        <select name="status" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </form>

    <div class="user-count">{{ $users->total() }} users</div>

    <!-- USER TABLE -->
    <div class="user-table-wrapper">
        <table class="user-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Active</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $avatarColors = ['#e74c3c','#8e44ad','#e67e22','#27ae60','#2980b9','#16a085','#d35400'];
                    $roleLabels = ['owner' => 'Farm Owner', 'manager' => 'Farm Manager', 'staff' => 'Farm Worker'];
                @endphp

                @forelse($users as $user)
                @php
                    $initials = collect(explode(' ', $user->name))->map(fn($w) => strtoupper(substr($w,0,1)))->take(2)->implode('');
                    $colorIndex = array_sum(array_map('ord', str_split($user->name))) % count($avatarColors);
                    $avatarColor = $avatarColors[$colorIndex];
                @endphp
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="avatar" style="background: {{ $avatarColor }}">{{ $initials }}</div>
                            <div class="user-info">
                                <strong>{{ $user->name }}</strong>
                                <span class="user-sub">{{ $user->username }} · {{ $user->email }}</span>
                            </div>
                        </div>
                    </td>
                    <td>
                        <span class="role-badge">{{ $roleLabels[$user->role] ?? $user->role }}</span>
                    </td>
                    <td>
                        <span class="status-dot status-{{ $user->status }}">
                            <i class="fas fa-circle"></i> {{ ucfirst($user->status) }}
                        </span>
                    </td>
                    <td>
                        {{ $user->last_login_at ? \Carbon\Carbon::parse($user->last_login_at)->diffForHumans() : 'Never' }}
                    </td>
                    <td class="actions-cell">
    <button type="button" class="icon-btn icon-edit" title="Edit"
       onclick="openEditModal({{ $user->id }}, '{{ $user->name }}', '{{ $user->username }}', '{{ $user->email }}', '{{ $user->role }}')">
        <i class="fas fa-pen"></i>
    </button>

    <form action="{{ route('owner.users.toggleStatus', $user->id) }}" method="POST" style="display:inline;">
        @csrf
        @method('PATCH')
        <button type="submit"
                class="icon-btn {{ $user->status === 'active' ? 'icon-deactivate' : 'icon-reactivate' }}"
                title="{{ $user->status === 'active' ? 'Deactivate' : 'Reactivate' }}">
            <i class="fas {{ $user->status === 'active' ? 'fa-user-slash' : 'fa-user-check' }}"></i>
        </button>
    </form>
</td>
                </tr>
                @empty
                <tr><td colspan="5" style="text-align:center; padding:20px; color:#999;">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>


        <div class="pagination-bar">
            {{ $users->links() }}
        </div>
    </div>
    </div>

</div>

<!-- ADD USER MODAL -->
<div class="modal-overlay {{ $errors->any() && old('_form') === 'add' ? 'active' : '' }}" id="addModal">
    <div class="modal-box">
        <h3>Add User</h3>
        @if($errors->any())
        <div class="alert-errors">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        <form action="{{ route('owner.users.store') }}" method="POST">
            @csrf

            <label>Full Name</label>
            <input type="text" name="name" required>

            <label>Username</label>
            <input type="text" name="username" required>

            <label>Email</label>
            <input type="email" name="email" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <label>Role</label>
            <select name="role" required>
                <option value="owner">Farm Owner</option>
                <option value="manager">Farm Manager</option>
                <option value="staff">Farm Worker</option>
            </select>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                <button type="submit" class="btn-save">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT USER MODAL -->
<div class="modal-overlay" id="editModal">
    <div class="modal-box">
        <h3>Edit User</h3>
        <form id="editForm" method="POST">
            @csrf
            @method('PUT')

            <label>Full Name</label>
            <input type="text" name="name" id="editName" required>

            <label>Username</label>
            <input type="text" name="username" id="editUsername" required>

            <label>Email</label>
            <input type="email" name="email" id="editEmail" required>

            <label>New Password (leave blank to keep current)</label>
            <input type="password" name="password">

            <label>Role</label>
            <select name="role" id="editRole" required>
                <option value="owner">Farm Owner</option>
                <option value="manager">Farm Manager</option>
                <option value="staff">Farm Worker</option>
            </select>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeEditModal()">Cancel</button>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}
function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}

function openEditModal(id, name, username, email, role) {
    document.getElementById('editForm').action = `/owner/users/${id}`;
    document.getElementById('editName').value = name;
    document.getElementById('editUsername').value = username;
    document.getElementById('editEmail').value = email;
    document.getElementById('editRole').value = role;
    document.getElementById('editModal').classList.add('active');
}
function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
}

document.getElementById('addModal').addEventListener('click', function(e) {
    if (e.target === this) closeAddModal();
});
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeEditModal();
});

@if(session('success'))
Swal.fire({
    icon: 'success',
    title: 'Success!',
    text: @json(session('success')),
    confirmButtonColor: '#2E7D32',
    timer: 2000,
    timerProgressBar: true,
});
@endif
</script>
@endpush