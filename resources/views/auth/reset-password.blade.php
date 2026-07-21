<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - NB Poultry Farm</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

    <style>
        body{
            background:#f4f7f6;
            min-height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            font-family:Arial, Helvetica, sans-serif;
        }

        .reset-card{
            width:100%;
            max-width:420px;
            border:none;
            border-radius:15px;
            overflow:hidden;
        }

        .card-header{
            background:#2E7D32;
            color:#fff;
            text-align:center;
            padding:20px;
        }

        .card-header i{
            font-size:40px;
            margin-bottom:10px;
            display:block;
        }

        .form-control{
            height:48px;
            border-radius:10px;
        }

        .btn-success{
            background:#2E7D32;
            border:none;
            border-radius:10px;
            height:48px;
            font-weight:600;
        }

        .btn-success:hover{
            background:#256628;
        }
    </style>
</head>

<body>

<div class="card shadow reset-card">

    <div class="card-header">
        <i class="bi bi-shield-lock-fill"></i>
        <h4 class="mb-0">Reset Password</h4>
    </div>

    <div class="card-body p-4">

        <p class="text-center text-muted mb-4">
            Create a new password for your account.
        </p>

        @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
        @endif

        <form method="POST" action="{{ route('forgot.reset') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label">New Password</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-lock-fill"></i>
                    </span>
                    <input type="password"
                           name="password"
                           class="form-control"
                           placeholder="Enter new password"
                           required>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label">Confirm Password</label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-shield-lock-fill"></i>
                    </span>
                    <input type="password"
                           name="password_confirmation"
                           class="form-control"
                           placeholder="Confirm password"
                           required>
                </div>
            </div>

            <button type="submit" class="btn btn-success w-100">
                <i class="bi bi-check-circle me-2"></i>
                Update Password
            </button>

        </form>

    </div>

</div>

</body>
</html>