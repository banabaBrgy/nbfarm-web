@extends('layouts.sidebar')

@section('title','Scan QR')

@section('content')

<div class="container-fluid">

    <!-- Header -->

    <div class="d-flex justify-content-between align-items-center mb-4">

        <div>

            <h2 class="fw-bold">

                QR Code Scanner

            </h2>

            <p class="text-muted mb-0">

                Scan the QR Code attached to the egg stack.

            </p>

        </div>

        <a href="{{ route(auth()->user()->role . '.production') }}" class="btn btn-secondary">

            <i class="fas fa-arrow-left"></i>

            Back

        </a>

    </div>


    <!-- Scanner -->

    <div class="card shadow-sm border-0 rounded-4">

        <div class="card-body text-center">

            <div id="reader"
                style="
                width:100%;
                max-width:650px;
                margin:auto;
                ">
            </div>

            <hr>

            <h5 class="fw-bold">

                Waiting for QR Code...

            </h5>

            <p class="text-muted">

                Position the QR Code inside the camera frame.

            </p>

        </div>

    </div>


    <!-- Result -->

    <div class="card shadow-sm border-0 rounded-4 mt-4">

        <div class="card-header bg-success text-white">

            <h5 class="mb-0">

                Scan Result

            </h5>

        </div>

        <div class="card-body">

            <table class="table">

                <tr>

                    <th width="180">

                        QR Code

                    </th>

                    <td id="qrCode">

                        -

                    </td>

                </tr>

                <tr>

                    <th>

                        Batch

                    </th>

                    <td id="batch">

                        -

                    </td>

                </tr>

                <tr>

                    <th>

                        Egg Size

                    </th>

                    <td id="size">

                        -

                    </td>

                </tr>

                <tr>

                    <th>

                        Total Eggs

                    </th>

                    <td id="eggs">

                        -

                    </td>

                </tr>

                <tr>

                    <th>

                        Status

                    </th>

                    <td id="status">

                        Waiting...

                    </td>

                </tr>

            </table>

        </div>

    </div>

</div>

@endsection


@push('scripts')

<script src="https://unpkg.com/html5-qrcode"></script>

<script src="https://unpkg.com/html5-qrcode"></script>

<<script src="https://unpkg.com/html5-qrcode"></script>

<script src="https://unpkg.com/html5-qrcode"></script>

<script>

let scannerPaused = false;

function onScanSuccess(decodedText) {

    // Huwag mag-scan ulit habang may popup
    if (scannerPaused) {
        return;
    }

    scannerPaused = true;

    // I-pause ang camera
    html5QrcodeScanner.pause(true);

    fetch("{{ route(auth()->user()->role . '.scan.store') }}", {

        method: "POST",

        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": "{{ csrf_token() }}"
        },

        body: JSON.stringify({
            qr_code: decodedText
        })

    })

    .then(response => response.json())

    .then(res => {

        if(res.success){
 
document.getElementById("status").innerHTML = `
<div class="alert alert-success mt-3">

    <strong>Scan Successful!</strong><br>

    <b>QR Code:</b> ${res.qr_code}<br>

    <b>Batch:</b> ${res.batch}<br>

    <b>Egg Size:</b> ${res.size}<br>

    <b>Total Eggs:</b> ${res.eggs}

</div>
`;

            Swal.fire({

                icon: 'success',

                title: 'QR Successfully Scanned',

                html: `
                    <div style="font-size:16px">

                        <strong>${res.qr_code}</strong><br><br>

                        Batch :
                        <strong>${res.batch}</strong><br>

                        Egg Size :
                        <strong>${res.size}</strong><br>

                        Total Eggs :
                        <strong>${res.eggs}</strong>

                    </div>
                `,

                confirmButtonText: 'OK',

                allowOutsideClick: false,
                allowEscapeKey: false,
                allowEnterKey: false

            }).then(() => {

                scannerPaused = false;

                html5QrcodeScanner.resume();

            });

        }else{

        document.getElementById("status").innerHTML = `
<div class="alert alert-danger mt-3">

${res.message}

</div>
`;
            Swal.fire({

                icon:'error',

                title:'Scan Failed',

                text:res.message,

                confirmButtonText:'OK',

                allowOutsideClick:false,
                allowEscapeKey:false

                }).then(()=>{

                scannerPaused = false;

                html5QrcodeScanner.resume();

            });

        }

    })

    .catch(error=>{

        console.log(error);

        Swal.fire({

            icon:'error',

            title:'Server Error',

            text:'Something went wrong.',

            confirmButtonText:'OK',

            allowOutsideClick:false

        }).then(()=>{

            scannerPaused = false;

            html5QrcodeScanner.resume();

        });

    });

}

let html5QrcodeScanner = new Html5QrcodeScanner(

    "reader",

    {
        fps:10,
        qrbox:250
    },

    false

);

html5QrcodeScanner.render(onScanSuccess);

</script>
@endpush