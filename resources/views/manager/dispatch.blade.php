@extends('layouts.sidebar')

@section('content')
@section('title', 'Dispatch')
@section('page-label', 'Dispatch')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/dispatch.css') }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
@endpush

<div class="page-header">
    <h1 class="page-title">Dispatch Management</h1>

    <button
    type="button"
    class="btn-primary"
    data-bs-toggle="modal"
    data-bs-target="#newDispatchModal">

    <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
        <line x1="12" y1="5" x2="12" y2="19"/>
        <line x1="5" y1="12" x2="19" y2="12"/>
    </svg>

    New Dispatch
</button>
</div>
<div class="dispatch-summary">

        <div class="summary-card">
            <i class="fas fa-truck"></i>

            <div>
                <span>Total Dispatch</span>
                <h3>{{ $dispatches->total() }}</h3>
            </div>
        </div>

        <div class="summary-card pending">
            <i class="fas fa-clock"></i>

            <div>
                <span>Pending</span>
                <h3>{{ $dispatches->where('status', 'Pending')->count() }}</h3>
            </div>
        </div>

        <div class="summary-card transit">
            <i class="fas fa-shipping-fast"></i>

            <div>
                <span>On Transit</span>
                <h3>{{ $dispatches->where('status', 'On Transit')->count() }}</h3>
            </div>
        </div>

        <div class="summary-card delivered">
            <i class="fas fa-check-circle"></i>

            <div>
                <span>Delivered</span>
                <h3>{{ $dispatches->where('status', 'Delivered')->count() }}</h3>
            </div>
        </div>
<div class="dispatch-header">

    <form method="GET" action="{{ route('manager.dispatch') }}" class="dispatch-filters">

        <input
            type="text"
            name="search"
            class="search-box"
            placeholder="Search recipient..."
            value="{{ request('search') }}">

        <select name="status" class="sort-select" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="Pending" {{ request('status')=='Pending'?'selected':'' }}>Pending</option>
            <option value="On Transit" {{ request('status')=='On Transit'?'selected':'' }}>On Transit</option>
            <option value="Delivered" {{ request('status')=='Delivered'?'selected':'' }}>Delivered</option>
        </select>

    </form>

</div>

<div class="table-container">

<table class="table">

    <thead>
    <tr>
        <th>Date</th>
        <th>Recipient</th>
        <th>Address</th>
        <th>Size</th>
        <th>Qty</th>
        <th>Price</th>
        <th>Status</th>
        <th>Action</th>
    </tr>
</thead>

    <tbody>
@forelse($dispatches as $dispatch)
<tr>

    <td>{{ \Carbon\Carbon::parse($dispatch->dispatch_date)->format('M d') }}</td>

    <td class="recipient">
        {{ $dispatch->recipient }}
    </td>

    <td>
        {{ $dispatch->address }}
    </td>

    {{-- SIZE --}}
    <td class="size-list">
        @foreach($dispatch->items as $item)
            <div>{{ $item->size }}</div>
        @endforeach
    </td>

    {{-- QTY --}}
    <td class="qty-list">
        @foreach($dispatch->items as $item)
            <div>{{ $item->quantity }} trays</div>
        @endforeach
    </td>

    {{-- PRICE --}}
    <td>
        ₱{{ number_format($dispatch->price, 2) }}
    </td>

    {{-- STATUS --}}
    <td>
        @if($dispatch->status == 'Pending')
            <span class="badge badge-warning">Pending</span>
        @elseif($dispatch->status == 'On Transit')
            <span class="badge badge-info">On Transit</span>
        @elseif($dispatch->status == 'Delivered')
            <span class="badge badge-success">Delivered</span>
        @endif
        
    </td>

    <td class="action-cell">

        <button type="button"
            class="icon-btn icon-btn-edit"
            data-bs-toggle="modal"
            data-bs-target="#editDispatchModal{{ $dispatch->id }}">
            <i class="fas fa-pen"></i>
        </button>

        <form action="{{ route('manager.dispatch.destroy', $dispatch->id) }}"
              method="POST"
              class="delete-dispatch-form"
              style="display:contents;">
            @csrf
            @method('DELETE')

            <button type="button"
                    class="icon-btn icon-btn-delete btn-delete-dispatch">
                <i class="fas fa-trash"></i>
            </button>
        </form>

    </td>

</tr>

    @empty

    <tr>
        <td colspan="6" class="text-center">
            No dispatch records found.
        </td>
    </tr>

    @endforelse

    </tbody>

</table>

<div class="dispatch-pagination">
    <div class="pagination-info">
        Showing {{ $dispatches->firstItem() ?? 0 }}–{{ $dispatches->lastItem() ?? 0 }} of {{ $dispatches->total() }}
    </div>

    <div class="pagination-controls">

        @if ($dispatches->onFirstPage())
            <span class="page-btn disabled">Prev</span>
        @else
            <a href="{{ $dispatches->previousPageUrl() }}" class="page-btn">Prev</a>
        @endif

        <span class="page-number">
            Page {{ $dispatches->currentPage() }} of {{ $dispatches->lastPage() }}
        </span>

        @if ($dispatches->hasMorePages())
            <a href="{{ $dispatches->nextPageUrl() }}" class="page-btn">Next</a>
        @else
            <span class="page-btn disabled">Next</span>
        @endif

    </div>
</div>

{{-- ===========================
    NEW DISPATCH MODAL
=========================== --}}

<div class="modal fade" id="newDispatchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">

        <form action="{{ route('manager.dispatch.store') }}" method="POST">

            @csrf

            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">
                        New Dispatch
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Recipient
                            </label>

                            <input
                                type="text"
                                name="recipient"
                                class="form-control"
                                required>

                        </div>

                        <div class="col-md-6 mb-3">

                            <label class="form-label">
                                Dispatch Date
                            </label>

                            <input
                                type="date"
                                name="dispatch_date"
                                class="form-control"
                                required>

                        </div>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Address
                        </label>

                        <textarea
                            name="address"
                            class="form-control"
                            rows="2"
                            required></textarea>

                    </div>

                    <div class="mb-3">

                        <label class="form-label">
                            Price
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            name="price"
                            class="form-control"
                            required>

                    </div>
<div class="mb-3">

    <label class="form-label">
        Status
    </label>

    <select
        name="status"
        class="form-select"
        required>

        <option value="Pending" selected>
            Pending
        </option>

        <option value="On Transit">
            On Transit
        </option>

        <option value="Delivered">
            Delivered
        </option>

    </select>

</div>
                    <hr>

                    <h6 class="mb-3">
                        Dispatch Items
                    </h6>

                    <div id="itemsWrapper">

                        <div class="row item-row mb-2">

                            <div class="col-md-5">

                                <select
                                    name="size[]"
                                    class="form-select"
                                    required>

                                    <option value="">
                                        Select Size
                                    </option>

                                    <option value="Small">
                                        Small
                                    </option>

                                    <option value="Medium">
                                        Medium
                                    </option>

                                    <option value="Large">
                                        Large
                                    </option>

                                </select>

                            </div>

                            <div class="col-md-5">

                                <input
                                    type="number"
                                    min="1"
                                    name="quantity[]"
                                    class="form-control"
                                    placeholder="Quantity"
                                    required>

                            </div>

                            <div class="col-md-2">

                                <button
                                    type="button"
                                    class="btn btn-danger removeItem"
                                    disabled>

                                    Remove

                                </button>

                            </div>

                        </div>

                    </div>

                    <button
                        type="button"
                        class="btn btn-secondary mt-2"
                        id="addItem">

                        + Add Item

                    </button>

                </div>

                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal">

                        Cancel

                    </button>

                    <button
                        type="submit"
                        class="btn btn-success">

                        Save Dispatch

                    </button>

                </div>

            </div>

        </form>

    </div>
</div>

{{-- MODALS: outside the table, kept as a separate loop --}}
@foreach($dispatches as $dispatch)
<div class="modal fade" id="editDispatchModal{{ $dispatch->id }}" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('owner.dispatch.update', $dispatch->id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="modal-content">

                <div class="modal-header">
                    <h5 class="modal-title">Edit Dispatch</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <div class="mb-3">
                        <label class="form-label">Recipient</label>
                        <input type="text"
                               class="form-control"
                               value="{{ $dispatch->recipient }}"
                               readonly>
                    </div>

                    <div class="mb-3">
    <label class="form-label">Dispatch Date</label>
    <input type="date"
           name="dispatch_date"
           class="form-control"
           value="{{ \Carbon\Carbon::parse($dispatch->dispatch_date)->format('Y-m-d') }}">
</div>

                    <div class="mb-3">
                        <label class="form-label">Items</label>
                        @foreach($dispatch->items as $item)
                            <div>{{ $item->size }} - {{ $item->quantity }} trays</div>
                        @endforeach
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-select">
                            <option value="On Transit" {{ $dispatch->status == 'On Transit' ? 'selected' : '' }}>On Transit</option>
                            <option value="Delivered" {{ $dispatch->status == 'Delivered' ? 'selected' : '' }}>Delivered</option>
                            <option value="Pending" {{ $dispatch->status == 'Pending' ? 'selected' : '' }}>Pending</option>
                        </select>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Save Changes</button>
                </div>

            </div>

        </form>
    </div>
</div>
@endforeach

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {

        // Delete confirmation
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

        @if(session('success'))
            Swal.fire({
                icon: 'success',
                title: 'Success',
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
// ======================
// ADD / REMOVE ITEM ROWS
// ======================

const addItemBtn = document.getElementById('addItem');
const itemsWrapper = document.getElementById('itemsWrapper');

if (addItemBtn && itemsWrapper) {

    addItemBtn.addEventListener('click', function () {

        const row = document.createElement('div');

        row.className = 'row item-row mb-2';

        row.innerHTML = `
            <div class="col-md-5">
                <select name="size[]" class="form-select" required>
                    <option value="">Select Size</option>
                    <option value="Small">Small</option>
                    <option value="Medium">Medium</option>
                    <option value="Large">Large</option>
                </select>
            </div>

            <div class="col-md-5">
                <input
                    type="number"
                    name="quantity[]"
                    class="form-control"
                    min="1"
                    placeholder="Quantity"
                    required>
            </div>

            <div class="col-md-2">
                <button
                    type="button"
                    class="btn btn-danger removeItem">
                    Remove
                </button>
            </div>
        `;

        itemsWrapper.appendChild(row);

    });

    itemsWrapper.addEventListener('click', function(e){

        if(e.target.classList.contains('removeItem')){

            e.target.closest('.item-row').remove();

        }

    });

}
    });
</script>
@endpush

@endsection