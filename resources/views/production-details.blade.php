@extends('layouts.sidebar')

@section('title','Batch Details')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/production-details.css') }}">
@endpush

@section('content')

<div class="production-details-page">

    <a href="{{ route(\App\Models\User::routeRole() . '.production', ['date' => $production->production_date]) }}" class="btn-back mb-3">
        <i class="fas fa-arrow-left"></i>
        Back
    </a>

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

            QR Records

        </div>

        <div class="card-body">

            <table>

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
                                class="btn-edit editQR"
                                data-id="{{ $transaction->id }}"
                                data-code="{{ $transaction->qrRecord->qr_code }}"
                                data-size="{{ $transaction->qrRecord->egg_size }}"
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

            <div class="pagination-wrapper">

                {{ $transactions->links() }}

            </div>

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

        document.getElementById('qr_code').value=this.dataset.code;

        document.getElementById('egg_size').value=this.dataset.size;

        document.getElementById('total_eggs').value=this.dataset.eggs;

        modal.show();

    });

});

document.getElementById('saveQR').addEventListener('click', function () {

    let id = document.getElementById('transaction_id').value;

    fetch('/qr/update/' + id, {

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

        console.log("STATUS:", response.status);

        const text = await response.text();

        console.log("RAW RESPONSE:", text);

        return JSON.parse(text);

    })

    .then(data => {

        if(data.success){

            modal.hide();

            setTimeout(() => {

                location.reload();

            },300);

        }

    })

    .catch(error => {

        console.error(error);

        alert("Something went wrong.");

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