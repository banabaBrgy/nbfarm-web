@php
    $rolePrefix = \App\Models\User::routeRole();
    $canManage = $canManage ?? ($rolePrefix === 'manager');
    $indexRoute = $rolePrefix . '.dispatch';
    $sizes = ['Small', 'Medium', 'Large', 'Extra Large'];
    $hasFilters = request()->filled('search') || request()->filled('status') || request()->filled('size');
    $availableDispatchTrays = array_sum(
        \App\Models\Production::availableDispatchTraysBySize()
    );
@endphp

<div class="dispatch-page">
    @if($errors->any())
        <div class="dsp-alert dsp-alert-error" role="alert">
            {{ $errors->first() }}
        </div>
    @endif


    <div class="page-header">
        <div class="page-header-copy">
            <h2>Dispatch</h2>
            <p>Track outgoing egg deliveries by recipient, size, and status.</p>
        </div>

        @if($canManage)
            <button
                type="button"
                class="btn-new"
                data-bs-toggle="modal"
                data-bs-target="#newDispatchModal"
                @disabled($availableDispatchTrays < 1)
                title="{{ $availableDispatchTrays > 0 ? 'Create a new dispatch' : 'No egg trays are available for dispatch' }}"
            >
                <i class="fas fa-plus"></i>
                {{ $availableDispatchTrays > 0 ? 'New Dispatch' : 'No Egg Stock' }}
            </button>
        @endif
    </div>

    <div class="dispatch-stats">
        <div class="dsp-stat dsp-stat-total">
            <div class="dsp-stat-icon"><i class="fas fa-truck"></i></div>
            <div>
                <div class="dsp-stat-label">Total</div>
                <div class="dsp-stat-value">{{ number_format($dispatchStats['total'] ?? 0) }}</div>
            </div>
        </div>

        <div class="dsp-stat dsp-stat-pending">
            <div class="dsp-stat-icon"><i class="fas fa-clock"></i></div>
            <div>
                <div class="dsp-stat-label">Pending</div>
                <div class="dsp-stat-value">{{ number_format($dispatchStats['pending'] ?? 0) }}</div>
            </div>
        </div>

        <div class="dsp-stat dsp-stat-transit">
            <div class="dsp-stat-icon"><i class="fas fa-shipping-fast"></i></div>
            <div>
                <div class="dsp-stat-label">On Transit</div>
                <div class="dsp-stat-value">{{ number_format($dispatchStats['transit'] ?? 0) }}</div>
            </div>
        </div>

        <div class="dsp-stat dsp-stat-delivered">
            <div class="dsp-stat-icon"><i class="fas fa-check-circle"></i></div>
            <div>
                <div class="dsp-stat-label">Delivered</div>
                <div class="dsp-stat-value">{{ number_format($dispatchStats['delivered'] ?? 0) }}</div>
            </div>
        </div>
    </div>

    <div class="dsp-card">
        <div class="dsp-toolbar">
            <h2>Delivery records</h2>

            <div class="dsp-toolbar-right">
            <form method="GET" action="{{ route($indexRoute) }}" class="dsp-filters">
                <div class="dsp-search">
                    <i class="fas fa-search"></i>
                    <input
                        type="text"
                        name="search"
                        placeholder="Search recipient..."
                        value="{{ request('search') }}">
                </div>

                <select name="status" data-auto-submit>
                    <option value="">All status</option>
                    <option value="Pending" @selected(request('status') === 'Pending')>Pending</option>
                    <option value="On Transit" @selected(request('status') === 'On Transit')>On Transit</option>
                    <option value="Delivered" @selected(request('status') === 'Delivered')>Delivered</option>
                </select>

                <select name="size" data-auto-submit>
                    <option value="">All sizes</option>
                    @foreach($sizes as $size)
                        <option value="{{ $size }}" @selected(request('size') === $size)>{{ $size }}</option>
                    @endforeach
                </select>

                @if($hasFilters)
                    <a href="{{ route($indexRoute) }}" class="dsp-clear">Clear</a>
                @endif
            </form>

            @if($canManage)
                <button type="button" id="bulkDeleteBtn" class="dsp-bulk-delete" disabled>
                    <i class="fas fa-trash"></i>
                    Delete selected (<span id="bulkDeleteCount">0</span>)
                </button>
                <form id="bulkDeleteForm" method="POST" action="{{ route($rolePrefix . '.dispatch.destroyBulk') }}" hidden>
                    @csrf
                    @method('DELETE')
                </form>
            @endif
            </div>
        </div>

        <div class="dsp-table-wrap">
            <table class="dsp-table has-action-column">
                <colgroup>
                    @if($canManage)
                        <col class="dsp-col-check">
                    @endif
                    <col class="dsp-col-date">
                    <col class="dsp-col-recipient">
                    <col class="dsp-col-address">
                    <col class="dsp-col-items">
                    <col class="dsp-col-price">
                    <col class="dsp-col-status">
                    <col class="dsp-col-action">
                </colgroup>
                <thead>
                    <tr>
                        @if($canManage)
                            <th class="dsp-check-col">
                                <input type="checkbox" id="selectAllDispatches" title="Select all" aria-label="Select all">
                            </th>
                        @endif
                        <th>Date</th>
                        <th>Recipient</th>
                        <th>Address</th>
                        <th>Items</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th class="dsp-action-col">{{ $canManage ? 'Action' : 'View' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dispatches as $dispatch)
                        @php
                            $date = \Carbon\Carbon::parse($dispatch->dispatch_date);
                            $statusKey = match ($dispatch->status) {
                                'On Transit' => 'transit',
                                'Delivered' => 'delivered',
                                default => 'pending',
                            };
                        @endphp
                        <tr>
                            @if($canManage)
                                <td class="dsp-check-col">
                                    <input
                                        type="checkbox"
                                        class="dsp-row-check"
                                        value="{{ $dispatch->id }}"
                                        aria-label="Select {{ $dispatch->recipient }}">
                                </td>
                            @endif
                            <td>
                                <div class="dsp-date">
                                    <strong>{{ $date->format('M d, Y') }}</strong>
                                </div>
                            </td>
                            <td class="dsp-recipient">{{ $dispatch->recipient }}</td>
                            <td>
                                <div class="dsp-address" title="{{ $dispatch->address }}">
                                    {{ $dispatch->address }}
                                </div>
                            </td>
                            <td>
                                <div class="dsp-items">
                                    @foreach($dispatch->items as $item)
                                        @php
                                            $chip = strtolower(str_replace(' ', '-', $item->size));
                                        @endphp
                                        <div class="dsp-item">
                                            <span class="dsp-chip dsp-chip-{{ $chip }}">{{ $item->size }}</span>
                                            <span class="dsp-qty">{{ $item->quantity }} trays</span>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                            <td class="dsp-price">₱{{ number_format($dispatch->price, 2) }}</td>
                            <td>
                                <span class="dsp-badge dsp-badge-{{ $statusKey }}">{{ $dispatch->status }}</span>
                            </td>
                            <td class="dsp-action-col">
                                <div class="dsp-actions">
                                    @if($canManage)
                                        <button
                                            type="button"
                                            class="dsp-icon-btn dsp-icon-edit"
                                            title="Edit"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editDispatchModal{{ $dispatch->id }}">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        <form
                                            action="{{ route($rolePrefix . '.dispatch.destroy', $dispatch->id) }}"
                                            method="POST"
                                            class="delete-dispatch-form">
                                            @csrf
                                            @method('DELETE')
                                            <button type="button" class="dsp-icon-btn dsp-icon-delete btn-delete-dispatch" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    @else
                                        <button
                                            type="button"
                                            class="dsp-icon-btn dsp-icon-view"
                                            title="View"
                                            data-bs-toggle="modal"
                                            data-bs-target="#viewDispatchModal{{ $dispatch->id }}">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $canManage ? 8 : 7 }}">
                                <div class="dsp-empty">
                                    <i class="fas fa-truck"></i>
                                    <p>No dispatch records found</p>
                                    <span>{{ $hasFilters ? 'Try a different search or clear the filters.' : 'Create a dispatch to get started.' }}</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($dispatches->total() > 0)
            <div class="dsp-pagination">
                <div class="dsp-pagination-info">
                    Showing {{ $dispatches->firstItem() }}–{{ $dispatches->lastItem() }} of {{ $dispatches->total() }}
                </div>
                <div class="dsp-pagination-controls">
                    @if ($dispatches->onFirstPage())
                        <span class="dsp-page-btn disabled">Prev</span>
                    @else
                        <a href="{{ $dispatches->previousPageUrl() }}" class="dsp-page-btn">Prev</a>
                    @endif

                    <span class="dsp-page-number">
                        Page {{ $dispatches->currentPage() }} of {{ $dispatches->lastPage() }}
                    </span>

                    @if ($dispatches->hasMorePages())
                        <a href="{{ $dispatches->nextPageUrl() }}" class="dsp-page-btn">Next</a>
                    @else
                        <span class="dsp-page-btn disabled">Next</span>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>

@if($canManage)
<div class="modal fade dispatch-modal" id="newDispatchModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <form action="{{ route($rolePrefix . '.dispatch.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Dispatch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Recipient</label>
                            <input type="text" name="recipient" class="form-control" placeholder="Customer or store name" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Dispatch Date</label>
                            <input type="date" name="dispatch_date" class="form-control" value="{{ now()->toDateString() }}" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="2" placeholder="Delivery address" required></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Price (₱)</label>
                            <input type="number" step="0.01" min="0" name="price" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="Pending" selected>Pending</option>
                                <option value="On Transit">On Transit</option>
                                <option value="Delivered">Delivered</option>
                            </select>
                        </div>
                    </div>

                    <div class="dsp-items-head">
                        <h6>Items</h6>
                        <button type="button" class="dsp-add-item" id="addItem">
                            <i class="fas fa-plus"></i> Add size
                        </button>
                    </div>

                    <div id="itemsWrapper">
                        <div class="dsp-item-row item-row">
                            <select name="size[]" class="form-select" required>
                                <option value="">Select size</option>
                                @foreach($sizes as $size)
                                    <option value="{{ $size }}">{{ $size }}</option>
                                @endforeach
                            </select>
                            <input type="number" min="1" name="quantity[]" class="form-control" placeholder="Trays" required>
                            <button type="button" class="dsp-remove-item removeItem" disabled>
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-save">Save Dispatch</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif

@foreach($dispatches as $dispatch)
    @if($canManage)
        <div class="modal fade dispatch-modal" id="editDispatchModal{{ $dispatch->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route($rolePrefix . '.dispatch.update', $dispatch->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Dispatch</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="dsp-field">
                                <label class="form-label">Recipient</label>
                                <input type="text" class="form-control dsp-readonly" value="{{ $dispatch->recipient }}" readonly>
                            </div>

                            <div class="dsp-field">
                                <label class="form-label">Address</label>
                                <input type="text" class="form-control dsp-readonly" value="{{ $dispatch->address }}" readonly>
                            </div>

                            <div class="dsp-field-row">
                                <div class="dsp-field">
                                    <label class="form-label">Dispatch Date</label>
                                    <input
                                        type="date"
                                        name="dispatch_date"
                                        class="form-control"
                                        value="{{ \Carbon\Carbon::parse($dispatch->dispatch_date)->format('Y-m-d') }}"
                                        required>
                                </div>
                                <div class="dsp-field">
                                    <label class="form-label">Price</label>
                                    <input type="text" class="form-control dsp-readonly" value="₱{{ number_format($dispatch->price, 2) }}" readonly>
                                </div>
                            </div>

                            <div class="dsp-field">
                                <label class="form-label">Items</label>
                                <div class="dsp-items-box">
                                    @foreach($dispatch->items as $item)
                                        @php $chip = strtolower(str_replace(' ', '-', $item->size)); @endphp
                                        <div class="dsp-items-box-row">
                                            <span class="dsp-chip dsp-chip-{{ $chip }}">{{ $item->size }}</span>
                                            <span class="dsp-items-box-qty">{{ number_format($item->quantity) }} trays</span>
                                        </div>
                                    @endforeach
                                </div>
                                <p class="dsp-field-hint">Size and trays cannot be changed here.</p>
                            </div>

                            <div class="dsp-field dsp-field-last">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-select">
                                    <option value="Pending" @selected($dispatch->status === 'Pending')>Pending</option>
                                    <option value="On Transit" @selected($dispatch->status === 'On Transit')>On Transit</option>
                                    <option value="Delivered" @selected($dispatch->status === 'Delivered')>Delivered</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn-cancel" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn-save">Save Changes</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    @else
        <div class="modal fade dispatch-modal" id="viewDispatchModal{{ $dispatch->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Dispatch Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="dsp-field">
                            <label class="form-label">Recipient</label>
                            <input type="text" class="form-control dsp-readonly" value="{{ $dispatch->recipient }}" readonly>
                        </div>
                        <div class="dsp-field">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control dsp-readonly" value="{{ $dispatch->address }}" readonly>
                        </div>
                        <div class="dsp-field-row">
                            <div class="dsp-field">
                                <label class="form-label">Dispatch Date</label>
                                <input
                                    type="text"
                                    class="form-control dsp-readonly"
                                    value="{{ \Carbon\Carbon::parse($dispatch->dispatch_date)->format('M d, Y') }}"
                                    readonly>
                            </div>
                            <div class="dsp-field">
                                <label class="form-label">Price</label>
                                <input type="text" class="form-control dsp-readonly" value="₱{{ number_format($dispatch->price, 2) }}" readonly>
                            </div>
                        </div>
                        <div class="dsp-field">
                            <label class="form-label">Items</label>
                            <div class="dsp-items-box">
                                @foreach($dispatch->items as $item)
                                    @php $chip = strtolower(str_replace(' ', '-', $item->size)); @endphp
                                    <div class="dsp-items-box-row">
                                        <span class="dsp-chip dsp-chip-{{ $chip }}">{{ $item->size }}</span>
                                        <span class="dsp-items-box-qty">{{ number_format($item->quantity) }} trays</span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="dsp-field dsp-field-last">
                            <label class="form-label">Status</label>
                            @php
                                $statusKey = match ($dispatch->status) {
                                    'On Transit' => 'transit',
                                    'Delivered' => 'delivered',
                                    default => 'pending',
                                };
                            @endphp
                            <div>
                                <span class="dsp-badge dsp-badge-{{ $statusKey }}">{{ $dispatch->status }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn-cancel" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endforeach

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.btn-delete-dispatch').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const form = btn.closest('form');
            Swal.fire({
                title: 'Delete this dispatch?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete it'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        });
    });

    const selectAll = document.getElementById('selectAllDispatches');
    const bulkBtn = document.getElementById('bulkDeleteBtn');
    const bulkForm = document.getElementById('bulkDeleteForm');
    const bulkCount = document.getElementById('bulkDeleteCount');
    const rowChecks = function () {
        return Array.from(document.querySelectorAll('.dsp-row-check'));
    };

    function syncBulkUi() {
        const checks = rowChecks();
        const selected = checks.filter(function (cb) { return cb.checked; });
        checks.forEach(function (cb) {
            cb.closest('tr').classList.toggle('dsp-row-selected', cb.checked);
        });
        if (bulkBtn && bulkCount) {
            bulkBtn.disabled = selected.length === 0;
            bulkCount.textContent = String(selected.length);
        }
        if (selectAll) {
            selectAll.checked = checks.length > 0 && selected.length === checks.length;
            selectAll.indeterminate = selected.length > 0 && selected.length < checks.length;
        }
    }

    if (selectAll) {
        selectAll.addEventListener('change', function () {
            rowChecks().forEach(function (cb) {
                cb.checked = selectAll.checked;
            });
            syncBulkUi();
        });
    }

    document.querySelectorAll('.dsp-row-check').forEach(function (cb) {
        cb.addEventListener('change', syncBulkUi);
    });

    if (bulkBtn && bulkForm) {
        bulkBtn.addEventListener('click', function () {
            if (bulkBtn.disabled) return;
            const ids = rowChecks().filter(function (cb) { return cb.checked; }).map(function (cb) { return cb.value; });
            if (!ids.length) return;

            Swal.fire({
                title: 'Delete ' + ids.length + ' dispatch' + (ids.length === 1 ? '' : 'es') + '?',
                text: 'This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc2626',
                cancelButtonColor: '#64748b',
                confirmButtonText: 'Yes, delete'
            }).then(function (result) {
                if (!result.isConfirmed) return;
                bulkForm.querySelectorAll('input[name="ids[]"]').forEach(function (el) { el.remove(); });
                ids.forEach(function (id) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'ids[]';
                    input.value = id;
                    bulkForm.appendChild(input);
                });
                bulkForm.submit();
            });
        });
    }

    @if(session('success'))
        Swal.fire({
            icon: 'success',
            title: 'Saved',
            text: @json(session('success')),
            timer: 2000,
            showConfirmButton: false
        });
    @endif

    @if(session('error'))
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: @json(session('error')),
            timer: 2500,
            showConfirmButton: false
        });
    @endif

    const addItemBtn = document.getElementById('addItem');
    const itemsWrapper = document.getElementById('itemsWrapper');
    const sizeOptions = @json($sizes);

    if (addItemBtn && itemsWrapper) {
        addItemBtn.addEventListener('click', function () {
            const options = sizeOptions.map(function (size) {
                return '<option value="' + size + '">' + size + '</option>';
            }).join('');

            const row = document.createElement('div');
            row.className = 'dsp-item-row item-row';
            row.innerHTML =
                '<select name="size[]" class="form-select" required>' +
                    '<option value="">Select size</option>' + options +
                '</select>' +
                '<input type="number" name="quantity[]" class="form-control" min="1" placeholder="Trays" required>' +
                '<button type="button" class="dsp-remove-item removeItem"><i class="fas fa-times"></i></button>';

            itemsWrapper.appendChild(row);
        });

        itemsWrapper.addEventListener('click', function (e) {
            const btn = e.target.closest('.removeItem');
            if (!btn || btn.disabled) return;
            const row = btn.closest('.item-row');
            if (row) row.remove();
        });
    }
});
</script>
@endpush
