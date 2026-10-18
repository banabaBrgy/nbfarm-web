@extends('layouts.sidebar')

@section('title','Batch Details')

@push('styles')
<link rel="stylesheet" href="{{ versioned_asset('css/production-details.css') }}">
@endpush

@section('content')

<div class="production-details-page">

    <div class="page-header">
        <div>
            <h2>Batch {{ $production->batch_id }}</h2>
            <p>Review and update this harvest record.</p>
        </div>
        <a href="{{ route(\App\Models\User::routeRole() . '.production', ['date' => $production->production_date]) }}" class="btn-back">
            <i class="fas fa-arrow-left"></i>
            Back
        </a>
    </div>

    <div class="detail-summary">

        <div>
            <div class="detail-card">
                <small>Batch</small>
                <h1>Batch {{ $production->batch_id }}</h1>
            </div>
        </div>

        <div>
            <div class="detail-card">
                <small>Total Eggs</small>
                <h1><x-egg-count :value="$production->totalEggCount()" /></h1>
            </div>
        </div>

        <div>
            <div class="detail-card">
                <small>Production Date</small>
                <h2>{{ $production->production_date }}</h2>
            </div>
        </div>

    </div>

    <div class="records-card">

        <div class="records-header">
            <h2>QR Records</h2>
        </div>

        <div class="card-body">

            <table class="has-action-column">

                <thead>

                    <tr>

                        <th>QR Code</th>
                        <th>Egg Size</th>
                        <th>Total Eggs</th>
                        <th width="120">Action</th>

                    </tr>

                </thead>

                <tbody>

                @forelse($transactions as $transaction)

                    <tr>

                        <td>{{ $transaction->qrRecord->qr_code }}</td>

                        <td>{{ $transaction->qrRecord->egg_size }}</td>

                        <td>{{ number_format($transaction->total_eggs) }}</td>

                        <td>

                            <button
                                type="button"
                                class="btn-edit editQR"
                                data-id="{{ $transaction->id }}"
                                data-code="{{ $transaction->qrRecord->qr_code }}"
                                data-size="{{ $transaction->qrRecord->egg_size }}"
                                data-update-url="{{ route(\App\Models\User::routeRole() . '.qr.update', ['id' => $transaction->id]) }}"
                                data-eggs="{{ $transaction->total_eggs }}">

                                Edit

                            </button>

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td colspan="4" class="text-center">

                            No QR Records

                        </td>

                    </tr>

                @endforelse

                @if($transactions->count()==0)

                    <tr>

                        <td colspan="4" class="text-center">

                            No QR Records

                        </td>

                    </tr>

                @endif

                </tbody>

            </table>

            @if($transactions->hasPages())
                <div class="details-pagination">
                    <div class="details-pagination-info">
                        Showing {{ $transactions->firstItem() }}–{{ $transactions->lastItem() }} of {{ $transactions->total() }}
                    </div>
                    <div class="details-pagination-controls">
                        @if ($transactions->onFirstPage())
                            <span class="details-page-btn disabled">Prev</span>
                        @else
                            <a href="{{ $transactions->previousPageUrl() }}" class="details-page-btn">Prev</a>
                        @endif

                        <span class="details-page-number">
                            Page {{ $transactions->currentPage() }} of {{ $transactions->lastPage() }}
                        </span>

                        @if ($transactions->hasMorePages())
                            <a href="{{ $transactions->nextPageUrl() }}" class="details-page-btn">Next</a>
                        @else
                            <span class="details-page-btn disabled">Next</span>
                        @endif
                    </div>
                </div>
            @endif

        </div>

    </div>

</div>

<!-- EDIT MODAL -->

<div class="modal fade" id="editQRModal">

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header">

                <h5>Edit QR Record</h5>

                <button
                    class="btn-close"
                    data-bs-dismiss="modal">
                </button>

            </div>

            <div class="modal-body">

                <input type="hidden" id="transaction_id">

                <div class="mb-3">

                    <label>QR Code</label>

                    <input
                        type="text"
                        id="qr_code"
                        class="form-control"
                        readonly>

                </div>

                <div class="mb-3">

                    <label>Egg Size</label>

                    <input
                        type="text"
                        id="egg_size"
                        class="form-control"
                        readonly>

                </div>

                <div class="mb-3">

                    <label>Total Eggs</label>

                    <input
                        type="number"
                        id="total_eggs"
                        min="0"
                        max="300"
                        class="form-control">

                </div>

            </div>

            <div class="modal-footer">

                <button
                    class="btn btn-secondary"
                    data-bs-dismiss="modal">

                    Cancel

                </button>

                <button
                    class="btn btn-success"
                    id="saveQR">

                    Save

                </button>

            </div>

        </div>

    </div>

</div>

<script>

const modal = new bootstrap.Modal(document.getElementById('editQRModal'));

document.querySelectorAll('.editQR').forEach(btn=>{

    btn.addEventListener('click',function(){

        document.getElementById('transaction_id').value=this.dataset.id;
        document.getElementById('transaction_id').dataset.updateUrl=this.dataset.updateUrl;

        document.getElementById('qr_code').value=this.dataset.code;

        document.getElementById('egg_size').value=this.dataset.size;

        document.getElementById('total_eggs').value=this.dataset.eggs;

        modal.show();

    });

});

document.getElementById('saveQR').addEventListener('click', function () {

    const transactionId = document.getElementById('transaction_id');
    const saveButton = this;

    saveButton.disabled = true;
    saveButton.textContent = 'Saving...';

    fetch(transactionId.dataset.updateUrl, {

        method: 'POST',

        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        },

        body: JSON.stringify({
            total_eggs: document.getElementById('total_eggs').value
        })

    })

    .then(async response => {
        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.message || 'Unable to save the edited egg count.');
        }

        return data;

    })

    .then(data => {

        if(data.success){

            modal.hide();

            Swal.fire({
                icon: 'success',
                title: 'Saved successfully',
                text: 'The egg count has been updated.',
                confirmButtonText: 'OK'
            }).then(() => {
                location.reload();
            });

        } else {
            throw new Error(data.message || 'Unable to save the edited egg count.');
        }

    })

    .catch(error => {

        console.error(error);

        saveButton.disabled = false;
        saveButton.textContent = 'Save';

        Swal.fire({
            icon: 'error',
            title: 'Save failed',
            text: error.message || 'Something went wrong.',
            confirmButtonText: 'OK'
        });

    });

});

document
.getElementById('editQRModal')
.addEventListener('hidden.bs.modal',function(){

    document.body.classList.remove('modal-open');

    document.body.removeAttribute('style');

    document
        .querySelectorAll('.modal-backdrop')
        .forEach(el=>el.remove());

});

</script>

@endsection