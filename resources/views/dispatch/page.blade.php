@php
    $rolePrefix = \App\Models\User::routeRole();
    $canManage = $canManage ?? ($rolePrefix === 'manager');
    $canEdit = $canEdit ?? $canManage;
    $indexRoute = $rolePrefix . '.dispatch';
    $sizes = ['Small', 'Medium', 'Large', 'Extra Large'];
    $hasFilters = request()->filled('search') || request()->filled('status') || request()->filled('size');
    $availableDispatchTrays = array_sum(
        \App\Models\Production::availableDispatchTraysBySize()
    );
    $createFailed = $errors->any() && old('_form') === 'create';
    $editFailedId = $errors->any() && old('_form') === 'edit' ? (int) old('dispatch_id') : null;
    $itemSizes = $createFailed ? array_values((array) old('size', [''])) : [''];
    $itemQuantities = $createFailed ? array_values((array) old('quantity', [''])) : [''];
    $itemCount = max(count($itemSizes), count($itemQuantities), 1);
@endphp

<div class="dispatch-page">
    @if($errors->any() && ! $createFailed && ! $editFailedId)
        <div class="dsp-alert dsp-alert-error" role="alert">
            <strong>The dispatch could not be saved.</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
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
                    <col class="dsp-col-contact">
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
                        <th>Created</th>
                        <th>Recipient</th>
                        <th>Contact</th>
                        <th>Address</th>
                        <th>Items</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th class="dsp-action-col">{{ $canManage || $canEdit ? 'Action' : 'View' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dispatches as $dispatch)
                        @php
                            $created = $dispatch->created_at ? \Carbon\Carbon::parse($dispatch->created_at) : null;
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
                                    <strong>{{ $created?->format('M d, Y') ?? '—' }}</strong>
                                    @if($created)
                                        <span>{{ $created->format('h:i A') }}</span>
                                    @endif
                                </div>
                            </td>
                            <td class="dsp-recipient">{{ $dispatch->recipient }}</td>
                            <td class="dsp-contact">{{ $dispatch->contact ?: 'N/A' }}</td>
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
                                    @if($canManage || $canEdit)
                                        <button
                                            type="button"
                                            class="dsp-icon-btn dsp-icon-edit"
                                            title="Edit"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editDispatchModal{{ $dispatch->id }}">
                                            <i class="fas fa-pen"></i>
                                        </button>
                                        @if(!$canManage)
                                            <button
                                                type="button"
                                                class="dsp-icon-btn dsp-icon-view"
                                                title="View"
                                                data-bs-toggle="modal"
                                                data-bs-target="#viewDispatchModal{{ $dispatch->id }}">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        @endif
                                        @if($canManage)
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
                                        @endif
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
                            <td colspan="{{ $canManage ? 9 : 8 }}">
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
        <form id="newDispatchForm" action="{{ route($rolePrefix . '.dispatch.store') }}" method="POST" novalidate>
            @csrf
            <input type="hidden" name="_form" value="create">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">New Dispatch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="dsp-form-errors" id="newDispatchErrors" role="alert" @unless($createFailed) hidden @endunless>
                        <strong>Please review the highlighted fields.</strong>
                        @if($createFailed)
                            <ul>
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="dispatchRecipient">Recipient</label>
                            <input
                                type="text"
                                id="dispatchRecipient"
                                name="recipient"
                                class="form-control @if($createFailed && $errors->has('recipient')) is-invalid @endif"
                                placeholder="Customer or store name"
                                value="{{ $createFailed ? old('recipient') : '' }}"
                                maxlength="255"
                                required>
                            @if($createFailed && $errors->has('recipient'))<p class="dsp-field-error">{{ $errors->first('recipient') }}</p>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="dispatchContact">Contact</label>
                            <input
                                type="tel"
                                id="dispatchContact"
                                name="contact"
                                class="form-control @if($createFailed && $errors->has('contact')) is-invalid @endif"
                                placeholder="09XX XXX XXXX"
                                value="{{ $createFailed ? old('contact') : '' }}"
                                maxlength="30"
                                inputmode="tel"
                                autocomplete="tel"
                                required>
                            @if($createFailed && $errors->has('contact'))<p class="dsp-field-error">{{ $errors->first('contact') }}</p>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="dispatchDate">Dispatch Date</label>
                            <input
                                type="date"
                                id="dispatchDate"
                                name="dispatch_date"
                                class="form-control @if($createFailed && $errors->has('dispatch_date')) is-invalid @endif"
                                value="{{ $createFailed ? old('dispatch_date') : now()->toDateString() }}"
                                required>
                            @if($createFailed && $errors->has('dispatch_date'))<p class="dsp-field-error">{{ $errors->first('dispatch_date') }}</p>@endif
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="dispatchAddress">Address</label>
                            <textarea
                                id="dispatchAddress"
                                name="address"
                                class="form-control @if($createFailed && $errors->has('address')) is-invalid @endif"
                                rows="2"
                                placeholder="Delivery address"
                                maxlength="255"
                                required>{{ $createFailed ? old('address') : '' }}</textarea>
                            @if($createFailed && $errors->has('address'))<p class="dsp-field-error">{{ $errors->first('address') }}</p>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="dispatchPrice">Price (₱)</label>
                            <input
                                type="number"
                                id="dispatchPrice"
                                step="0.01"
                                min="0"
                                name="price"
                                class="form-control @if($createFailed && $errors->has('price')) is-invalid @endif"
                                placeholder="0.00"
                                value="{{ $createFailed ? old('price') : '' }}"
                                required>
                            @if($createFailed && $errors->has('price'))<p class="dsp-field-error">{{ $errors->first('price') }}</p>@endif
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="dispatchStatus">Status</label>
                            <select id="dispatchStatus" name="status" class="form-select @if($createFailed && $errors->has('status')) is-invalid @endif" required>
                                @foreach(['Pending', 'On Transit', 'Delivered'] as $dispatchStatus)
                                    <option value="{{ $dispatchStatus }}" @selected(($createFailed ? old('status', 'Pending') : 'Pending') === $dispatchStatus)>{{ $dispatchStatus }}</option>
                                @endforeach
                            </select>
                            @if($createFailed && $errors->has('status'))<p class="dsp-field-error">{{ $errors->first('status') }}</p>@endif
                        </div>
                    </div>

                    <div class="dsp-items-head">
                        <h6>Items</h6>
                        <button type="button" class="dsp-add-item" id="addItem">
                            <i class="fas fa-plus"></i> Add size
                        </button>
                    </div>

                    @if($createFailed && $errors->has('quantity'))
                        <p class="dsp-field-error">{{ $errors->first('quantity') }}</p>
                    @endif
                    <div id="itemsWrapper">
                        @for($itemIndex = 0; $itemIndex < $itemCount; $itemIndex++)
                            <div class="dsp-item-row item-row">
                                <select name="size[]" class="form-select @if($createFailed && ($errors->has('size') || $errors->has('size.'.$itemIndex))) is-invalid @endif" required>
                                    <option value="">Select size</option>
                                    @foreach($sizes as $size)
                                        <option value="{{ $size }}" @selected(($itemSizes[$itemIndex] ?? '') === $size)>{{ $size }}</option>
                                    @endforeach
                                </select>
                                <input
                                    type="number"
                                    min="1"
                                    name="quantity[]"
                                    class="form-control @if($createFailed && ($errors->has('quantity.'.$itemIndex))) is-invalid @endif"
                                    placeholder="Trays"
                                    value="{{ $itemQuantities[$itemIndex] ?? '' }}"
                                    required>
                                <button type="button" class="dsp-remove-item removeItem" @disabled($itemCount === 1)>
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        @endfor
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
    @if($canManage || $canEdit)
        <div class="modal fade dispatch-modal" id="editDispatchModal{{ $dispatch->id }}" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <form action="{{ route($rolePrefix . '.dispatch.update', $dispatch->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="_form" value="edit">
                    <input type="hidden" name="dispatch_id" value="{{ $dispatch->id }}">
                    <input type="hidden" name="recipient" value="{{ $dispatch->recipient }}">
                    <input type="hidden" name="address" value="{{ $dispatch->address }}">
                    <input type="hidden" name="price" value="{{ $dispatch->price }}">
                    @foreach($dispatch->items as $item)
                        <input type="hidden" name="size[]" value="{{ $item->size }}">
                        <input type="hidden" name="quantity[]" value="{{ $item->quantity }}">
                    @endforeach
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Edit Dispatch</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            @if($editFailedId === $dispatch->id)
                                <div class="dsp-form-errors" role="alert">
                                    <strong>Please review the highlighted fields.</strong>
                                    <ul>
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
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
                                        class="form-control @if($editFailedId === $dispatch->id && $errors->has('dispatch_date')) is-invalid @endif"
                                        value="{{ $editFailedId === $dispatch->id ? old('dispatch_date') : \Carbon\Carbon::parse($dispatch->dispatch_date)->format('Y-m-d') }}"
                                        required>
                                    @if($editFailedId === $dispatch->id && $errors->has('dispatch_date'))
                                        <p class="dsp-field-error">{{ $errors->first('dispatch_date') }}</p>
                                    @endif
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
    @endif

    @if(!$canManage)
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
                            <label class="form-label">Contact</label>
                            <input type="text" class="form-control dsp-readonly" value="{{ $dispatch->contact ?: 'N/A' }}" readonly>
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.22.2/dist/sweetalert2.all.min.js" integrity="sha384-kR2tS24oEqwHzfBnAN4yxA8ib4vGEOMaANrncAlq8i0lsgvGoOzSPXFbj5dHYUZK" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    // Keep modals outside the padded app shell so fixed centering
    // uses the real viewport (same pattern as forecast modals).
    document.querySelectorAll('.dispatch-modal').forEach(function (modal) {
        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }
    });

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

    const openFailedModal = (modal) => {
        if (modal && window.bootstrap) {
            window.bootstrap.Modal.getOrCreateInstance(modal).show();
        }
    };

    if (@json($createFailed)) {
        openFailedModal(document.getElementById('newDispatchModal'));
    }

    if (@json((bool) $editFailedId)) {
        openFailedModal(document.getElementById('editDispatchModal{{ $editFailedId }}'));
    }

    const newDispatchForm = document.getElementById('newDispatchForm');
    if (newDispatchForm) {
        const contactPattern = /^(?=.*\d)[0-9+\-\s()]{7,30}$/;
        const errorBox = document.getElementById('newDispatchErrors');

        const showFieldError = (field, message) => {
            field.classList.toggle('is-invalid', Boolean(message));
            field.setAttribute('aria-invalid', message ? 'true' : 'false');
            const row = field.closest('.dsp-item-row');
            const host = row || field.parentElement;
            const existing = host.querySelector('.dsp-field-error[data-client="true"]');

            if (!message) {
                if (existing && (!row || existing.dataset.field === field.name)) {
                    existing.remove();
                }
                return;
            }

            const error = existing || document.createElement('p');
            error.className = 'dsp-field-error';
            error.dataset.client = 'true';
            error.dataset.field = field.name;
            error.textContent = message;
            host.appendChild(error);
        };

        newDispatchForm.addEventListener('input', (event) => {
            if (event.target === newDispatchForm.contact) {
                const cleaned = event.target.value.replace(/[^0-9+\-\s()]/g, '');
                if (cleaned !== event.target.value) {
                    event.target.value = cleaned;
                    showFieldError(event.target, 'Letters are not allowed in the contact number.');
                    return;
                }
            }

            if (event.target.matches('input, textarea, select')) {
                showFieldError(event.target, '');
            }
        });

        newDispatchForm.addEventListener('submit', (event) => {
            let firstInvalid = null;
            const requireText = (field, message) => {
                const invalid = field.value.trim() === '';
                showFieldError(field, invalid ? message : '');
                if (invalid && !firstInvalid) firstInvalid = field;
            };

            requireText(newDispatchForm.recipient, 'Recipient is required.');

            const contact = newDispatchForm.contact.value.trim();
            const contactDigits = contact.replace(/\D/g, '');
            const contactMessage = contact === ''
                ? 'Contact number is required.'
                : (!contactPattern.test(contact) || contactDigits.length < 10 || contactDigits.length > 13
                    ? 'Enter a contact number with 10 to 13 digits. Letters are not allowed.'
                    : '');
            showFieldError(newDispatchForm.contact, contactMessage);
            if (contactMessage && !firstInvalid) firstInvalid = newDispatchForm.contact;

            requireText(newDispatchForm.dispatch_date, 'Dispatch date is required.');
            requireText(newDispatchForm.address, 'Delivery address is required.');

            const price = newDispatchForm.price.value.trim();
            const priceMessage = price === ''
                ? 'Price is required.'
                : (Number(price) < 0 || Number.isNaN(Number(price)) ? 'Price must be a valid amount.' : '');
            showFieldError(newDispatchForm.price, priceMessage);
            if (priceMessage && !firstInvalid) firstInvalid = newDispatchForm.price;

            requireText(newDispatchForm.status, 'Status is required.');

            newDispatchForm.querySelectorAll('[name="size[]"]').forEach((field) => {
                const invalid = field.value === '';
                showFieldError(field, invalid ? 'Select an egg size.' : '');
                if (invalid && !firstInvalid) firstInvalid = field;
            });

            newDispatchForm.querySelectorAll('[name="quantity[]"]').forEach((field) => {
                const quantity = Number(field.value);
                const invalid = field.value.trim() === '' || !Number.isInteger(quantity) || quantity < 1;
                showFieldError(field, invalid ? 'Enter at least 1 tray.' : '');
                if (invalid && !firstInvalid) firstInvalid = field;
            });

            if (!firstInvalid) {
                return;
            }

            event.preventDefault();
            if (errorBox) {
                errorBox.hidden = false;
                errorBox.innerHTML = '<strong>Please review the highlighted fields.</strong>';
            }
            firstInvalid.focus();
        });
    }
});
</script>
@endpush
