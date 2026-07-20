<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>KPI KarSU</title>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
    <link rel="icon" href="{{ asset('dist/img/logo.png') }}">
</head>
<body class="hold-transition layout-top-nav">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand-md navbar-white navbar-light border-bottom">
        <div class="container-fluid px-3 px-md-4">
            <a href="{{ route('login') }}" class="navbar-brand font-weight-bold">
                KPI KarSU
            </a>
            <a href="{{ route('login.user') }}" class="btn btn-primary px-4">
                <i class="fas fa-sign-in-alt mr-1"></i> Kirish
            </a>
        </div>
    </nav>

    <div class="content-wrapper pt-4">
        <x-rating-list
            :$departments
            :$faculties
            :$filters
            :$report
            :$users
            filter-route="login"
        />
    </div>

    <footer class="main-footer">
        <strong>Qoraqalpoq davlat universiteti &copy; 2023-{{ date('Y') }} KPI KarSU.</strong>
    </footer>
</div>

<script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('dist/js/adminlte.min.js') }}"></script>
</body>
</html>
