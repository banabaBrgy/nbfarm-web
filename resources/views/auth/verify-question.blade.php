<!DOCTYPE html>
<html>
<head>

<title>Verify Account</title>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">


<style>

    body {

        background: #f4f7f6;
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;

    }


    .verify-card {

        width: 420px;
        border-radius: 15px;

    }


    .card-header {

        border-radius: 15px 15px 0 0 !important;

    }


    .question-box {

        background: #f8f9fa;
        border-left: 5px solid #198754;
        padding: 15px;
        border-radius: 8px;

    }


</style>


</head>


<body>


<div class="card shadow-lg border-0 verify-card">


    <div class="card-header bg-success text-white text-center p-3">

        <h3 class="mb-0">

            <i class="bi bi-shield-lock"></i>

            Verify Account

        </h3>

    </div>




    <div class="card-body p-4">



        @if(session('error'))

            <div class="alert alert-danger">

                {{ session('error') }}

            </div>

        @endif




        <div class="mb-3">

            <label class="fw-bold">

                Username

            </label>


            <input type="text"
                   class="form-control"
                   value="{{ $user->username }}"
                   readonly>

        </div>





        <div class="mb-3">


            <label class="fw-bold">

                Security Question

            </label>



            <div class="question-box mt-2">


                {{ $user->security_question }}


            </div>


        </div>







        <form method="POST" action="{{ route('forgot.verify') }}">


            @csrf





            <div class="mb-3">


                <label class="fw-bold">

                    Answer

                </label>




                <input type="text"
                       name="answer"
                       class="form-control"
                       placeholder="Enter your answer"
                       required>



            </div>


            <button type="submit"
                    class="btn btn-success w-100">


                <i class="bi bi-check-circle"></i>

                Verify

            </button>

        </form>

    </div>
</div>

</body>
</html>