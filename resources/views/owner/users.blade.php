@extends('layouts.sidebar')

@section('title', 'Users')
@section('page-label', 'Users')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('css/users.css') }}">
@endpush

@section('content')
<div class="users-page">

    <div class="page-header">
        <div>
            <h2>User Management</h2>
            <p>Add farm staff and manage account access.</p>
        </div>
        <button class="btn-update" type="button" onclick="openAddModal()">
            <i class="fas fa-plus"></i>
            Add User
        </button>
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
        <input type="text" name="search" placeholder="Search name or username..." value="{{ $search }}" data-auto-submit>

        <select name="status" data-auto-submit>
            <option value="">All Status</option>
            <option value="active" {{ $status === 'active' ? 'selected' : '' }}>Active</option>
            <option value="inactive" {{ $status === 'inactive' ? 'selected' : '' }}>Inactive</option>
        </select>
    </form>

    <!-- USER TABLE -->
    <div class="user-table-wrapper">
        <table class="user-table has-action-column">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Contact Number</th>
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
                    $contactNumber = $user->contact_number ? $user->formattedContactNumber() : '—';
                @endphp
                <tr>
                    <td>
                        <div class="user-cell">
                            <div class="avatar" style="background: {{ $avatarColor }}">{{ $initials }}</div>
                            <div class="user-info">
                                <strong>{{ $user->name }}</strong>
                                <span class="user-sub">{{ $user->username }} · {{ $user->email }}</span>
                                @if(! $user->hasSecurityAnswer())
                                    <span class="user-sub">Recovery question not set</span>
                                @endif
                            </div>
                        </div>
                    </td>
                    <td class="user-contact">{{ $contactNumber }}</td>
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
    <button type="button" class="icon-btn icon-edit js-edit-user" title="Edit"
       data-id="{{ $user->id }}"
       data-name="{{ $user->name }}"
       data-username="{{ $user->username }}"
       data-email="{{ $user->email }}"
       data-contact="{{ $user->contact_number ? $user->formattedContactNumber() : '' }}"
       data-role="{{ $user->role }}"
       data-question="{{ $user->security_question }}">
        <i class="fas fa-pen"></i>
    </button>

    <form action="{{ route('owner.users.toggleStatus', $user->id) }}" method="POST">
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
                <tr><td colspan="6" style="text-align:center; padding:20px; color:#999;">No users found.</td></tr>
                @endforelse
            </tbody>
        </table>


        <div class="pagination-bar">
            {{ $users->links() }}
        </div>
    </div>

</div>

<!-- ADD USER MODAL -->
<div class="modal-overlay {{ $errors->any() && old('_form') !== 'edit' ? 'active' : '' }}" id="addModal">
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
            <input type="hidden" name="_form" value="add">

            <label>Full Name</label>
            <input type="text" name="name" value="{{ old('_form') !== 'edit' ? old('name') : '' }}" required>

            <label>Username</label>
            <input type="text" name="username" value="{{ old('_form') !== 'edit' ? old('username') : '' }}" required>

            <label>Email</label>
            <input type="email" name="email" value="{{ old('_form') !== 'edit' ? old('email') : '' }}" required>

            <label>Password</label>
            <input type="password" name="password" required autocomplete="new-password">
            <span class="field-hint">At least 8 characters, with upper and lower case, a number, and a symbol.</span>

            <label>Security question</label>
            <input type="text" name="security_question" value="{{ old('_form') !== 'edit' ? old('security_question') : '' }}" maxlength="255" required>
            <span class="field-hint">The person must remember this question. The reset page asks only for the answer.</span>

            <label>Security answer</label>
            <input type="text" name="security_answer" minlength="8" maxlength="255" required autocomplete="off">
            <span class="field-hint">At least 8 characters. Five wrong tries from the same network pause recovery for 15 minutes.</span>

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
<div class="modal-overlay {{ $errors->any() && old('_form') === 'edit' ? 'active' : '' }}" id="editModal">
    <div class="modal-box">
        <h3>Edit User</h3>
        @if($errors->any() && old('_form') === 'edit')
        <div class="alert-errors">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
        @endif
        <form id="editForm" method="POST" action="{{ old('_form') === 'edit' ? route('owner.users.update', old('_user_id')) : '' }}">
            @csrf
            @method('PUT')
            <input type="hidden" name="_form" value="edit">
            <input type="hidden" name="_user_id" id="editUserId" value="{{ old('_form') === 'edit' ? old('_user_id') : '' }}">

            <label>Full Name</label>
            <input type="text" name="name" id="editName" value="{{ old('_form') === 'edit' ? old('name') : '' }}" required>

            <label>Username</label>
            <input type="text" name="username" id="editUsername" value="{{ old('_form') === 'edit' ? old('username') : '' }}" required>

            <label>Email</label>
            <input type="email" name="email" id="editEmail" value="{{ old('_form') === 'edit' ? old('email') : '' }}" required>

            <label>Contact number</label>
            <input type="text" name="contact_number" id="editContact" value="{{ old('_form') === 'edit' ? old('contact_number') : '' }}" maxlength="20" inputmode="tel" autocomplete="tel">
            <span class="field-hint">11-digit mobile number, like 0917 123 4567. Leave blank to clear it.</span>

            <label>New Password (leave blank to keep current)</label>
            <input type="password" name="password" autocomplete="new-password">
            <span class="field-hint">Leave blank to keep the current password. A new password needs 8 characters, upper and lower case, a number, and a symbol.</span>

            <label>Security question</label>
            <input type="text" name="security_question" id="editQuestion" value="{{ old('_form') === 'edit' ? old('security_question') : '' }}" maxlength="255">

            <label>Security answer</label>
            <input type="text" name="security_answer" maxlength="255" autocomplete="off">
            <span class="field-hint">Leave the answer blank to keep the current one. Fill both fields to set or change recovery.</span>

            <label>Role</label>
            <select name="role" id="editRole" required>
                <option value="owner" @selected(old('_form') === 'edit' && old('role') === 'owner')>Farm Owner</option>
                <option value="manager" @selected(old('_form') === 'edit' && old('role') === 'manager')>Farm Manager</option>
                <option value="staff" @selected(old('_form') === 'edit' && old('role') === 'staff')>Farm Worker</option>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.22.2/dist/sweetalert2.all.min.js" integrity="sha384-kR2tS24oEqwHzfBnAN4yxA8ib4vGEOMaANrncAlq8i0lsgvGoOzSPXFbj5dHYUZK" crossorigin="anonymous"></script>
<script>
function openAddModal() {
    document.getElementById('addModal').classList.add('active');
}
function closeAddModal() {
    document.getElementById('addModal').classList.remove('active');
}

function openEditModal(id, name, username, email, contact, role, question) {
    document.getElementById('editForm').action = `/owner/users/${id}`;
    document.getElementById('editUserId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editUsername').value = username;
    document.getElementById('editEmail').value = email;
    document.getElementById('editContact').value = contact || '';
    document.getElementById('editRole').value = role;
    document.getElementById('editQuestion').value = question || '';
    document.getElementById('editModal').classList.add('active');
}

document.querySelectorAll('.js-edit-user').forEach(function (button) {
    button.addEventListener('click', function () {
        openEditModal(
            button.dataset.id,
            button.dataset.name,
            button.dataset.username,
            button.dataset.email,
            button.dataset.contact,
            button.dataset.role,
            button.dataset.question
        );
    });
});
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
    confirmButtonColor: '#3B8F47',
    timer: 2000,
    timerProgressBar: true,
});
@endif

@if(session('error'))
Swal.fire({
    icon: 'error',
    title: 'Not allowed',
    text: @json(session('error')),
    confirmButtonColor: '#3B8F47',
});
@endif
</script>
@endpush