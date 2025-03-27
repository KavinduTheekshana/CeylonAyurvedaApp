<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Document</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <!-- Option 1: Include in HTML -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.3.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;700&display=swap" rel="stylesheet">

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous">
    </script>
    <link rel="stylesheet" href="{{ asset('frontend/css/styles.css') }}">
    {{-- @vite(['resources/js/app.js']) --}}


</head>

<body>
    <div class="container">
        <img src="{{ asset('frontend/assets/logo.svg') }}" alt="Ceylon Ayurveda Logo" class="logo">
        <div class="text">
            <h4 class="fw-bold">Login Account</h4>
            <p class="text-muted">Please login into your account</p>
        </div>
        <form>
            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="bi bi-person"></i></span>
                <input type="text" class="form-control-input" placeholder="steve.young@mail.com" required>
            </div>

            <div class="mb-3 input-group">
                <span class="input-group-text"><i class="bi bi-key"></i></span>
                <input type="password" class="form-control-input" placeholder="•••••••••••" required>
            </div>
            <a href="#" class="forgot-password">Forgot Password?</a>
            <button type="submit" class="btn btn-login mt-3">Login Account</button>
        </form>
        <p class="terms">By "Login Account", you agree to the <a href="#">Terms of Use</a> and <a
                href="#">Privacy Policy</a>.</p>
    </div>
</body>

</html>
