<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pharmacy System - Access denied</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-dark text-light">
<main class="container d-flex flex-column justify-content-center align-items-center text-center min-vh-100 gap-3">
    <h1 class="display-4">403</h1>
    <p class="fs-5">{{ __('You do not have access to this page.') }}</p>
    @auth
        <p class="text-secondary">{{ __('Signed in as :email', ['email' => auth()->user()->email]) }}</p>
        <div class="d-flex gap-2">
            <a href="{{ route('home') }}" class="btn btn-outline-light">{{ __('Go to home') }}</a>
            <form action="{{ route('logout') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-success">{{ __('Logout') }}</button>
            </form>
        </div>
    @else
        <a href="{{ route('login') }}" class="btn btn-success">{{ __('Login') }}</a>
    @endauth
</main>
</body>
</html>
