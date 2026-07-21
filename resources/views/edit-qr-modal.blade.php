<div class="modal fade" id="editQRModal" tabindex="-1">

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form id="editQRForm">

                @csrf

                <input type="hidden" id="transactionId" name="transaction_id">

                <div class="modal-header bg-success text-white">

                    <h5 class="modal-title">Edit QR Record</h5>

                    <button type="button"
                            class="btn-close btn-close-white"
                            data-bs-dismiss="modal">
                    </button>

                </div>

                <div class="modal-body">

                    <div class="mb-3">

                        <label>QR Code</label>

                        <input
                            type="text"
                            id="qrCode"
                            class="form-control"
                            readonly>

                    </div>

                    <div class="mb-3">

                        <label>Egg Size</label>

                        <input
                            type="text"
                            id="eggSize"
                            class="form-control"
                            readonly>

                    </div>

                    <div class="mb-3">

                        <label>Total Eggs</label>

                        <input
                            type="number"
                            id="totalEggs"
                            name="total_eggs"
                            class="form-control">

                    </div>

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

                        Save Changes

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>

<script>

document.addEventListener('DOMContentLoaded',function(){

    document.querySelectorAll('.editQR').forEach(btn=>{

        btn.addEventListener('click',function(){

            transactionId.value=this.dataset.id;
            qrCode.value=this.dataset.code;
            eggSize.value=this.dataset.size;
            totalEggs.value=this.dataset.eggs;

        });

    });

    editQRForm.addEventListener('submit',function(e){

        e.preventDefault();

        fetch("{{ route('qr.update') }}",{

            method:'POST',

            headers:{
                'X-CSRF-TOKEN':'{{ csrf_token() }}',
                'Accept':'application/json'
            },

            body:new FormData(this)

        })

        .then(r=>r.json())

        .then(res=>{

            bootstrap.Modal.getInstance(
                document.getElementById('editQRModal')
            ).hide();

            document.querySelector('.modal-backdrop')?.remove();

            document.body.classList.remove('modal-open');

            document.body.style='';

            location.reload();

        });

    });

});

</script>