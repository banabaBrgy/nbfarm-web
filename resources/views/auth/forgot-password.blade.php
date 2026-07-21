<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Forgot Password - NB Poultry Farm</title>


    <style>

        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
            font-family:Arial, Helvetica, sans-serif;
        }


        body{

            background:#eef2f3;
            display:flex;
            justify-content:center;
            align-items:center;
            height:100vh;

        }


        .container{

            width:400px;
            background:white;
            padding:35px;
            border-radius:12px;
            box-shadow:0 8px 20px rgba(0,0,0,.15);

        }


        h2{

            text-align:center;
            color:#2E7D32;
            margin-bottom:10px;

        }


        p{

            text-align:center;
            color:#666;
            margin-bottom:25px;

        }


        label{

            font-weight:bold;
            display:block;
            margin-bottom:6px;

        }


        input{

            width:100%;
            padding:12px;
            border:1px solid #ccc;
            border-radius:6px;
            margin-bottom:20px;

        }


        button{

            width:100%;
            padding:12px;
            background:#2E7D32;
            color:white;
            border:none;
            border-radius:6px;
            cursor:pointer;
            font-weight:bold;
            font-size:16px;

        }


        button:hover{

            background:#256628;

        }


        .back{

            text-align:center;
            margin-top:20px;

        }


        .back a{

            color:#2E7D32;
            text-decoration:none;

        }


        .error{

            background:#ffebee;
            color:#c62828;
            padding:10px;
            border-radius:6px;
            margin-bottom:15px;
            text-align:center;

        }


    </style>

</head>


<body>


<div class="container">


    <h2>
        Forgot Password
    </h2>


    <p>
        Enter your username to reset your password.
    </p>



    @if(session('error'))

        <div class="error">

            {{ session('error') }}

        </div>

    @endif



    <form method="POST" action="{{ route('forgot.check') }}">

        @csrf



        <label>
            Username
        </label>


        <input
            type="text"
            name="username"
            placeholder="Enter your username"
            required>



        <button type="submit">

            Continue

        </button>



    </form>




    <div class="back">

        <a href="/login">
            Back to Login
        </a>

    </div>



</div>


</body>

</html>