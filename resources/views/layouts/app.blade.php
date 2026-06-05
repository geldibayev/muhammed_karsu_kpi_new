<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="{{ asset('plugins/fontawesome-free/css/all.min.css') }}">
    <link rel="stylesheet" href="{{ asset('dist/css/adminlte.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/toastr/toastr.min.css') }}">
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item">
                <a href="{{ route('profile') }}" class="nav-link">
                    <p>{{ auth()->user()->short }}</p>
                </a>
            </li>
        </ul>
    </nav>

    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="{{ url('/') }}" class="brand-link">
            <img src="{{ asset('dist/img/AdminLTELogo.png') }}" alt="AdminLTE Logo"
                 class="brand-image img-circle elevation-3"
                 style="opacity: .8">
            <span class="brand-text font-weight-light">KarSU KPI</span>
        </a>
        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu"
                    data-accordion="true" style="font-size: 14px;">
                    <li class="nav-header font-weight-bold" style="text-transform: uppercase">
                        Asosiy
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('home') }}" class="nav-link @if(request()->routeIs('home')) active @endif">
                            <i class="nav-icon fas fa-home"></i>
                            <p>Asosiy sahifa</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="nav-icon fa fa-layer-group"></i>
                            <p>
                                Resurslar
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('files.show', 'received') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Yuborilgan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('files.show', 'checking') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Tekshirilmoqda</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('files.show', 'accepted') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Tasdiqlangan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('files.show', 'cancelled') }}" class="nav-link">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Qaytarilgan</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <li class="nav-header font-weight-bold" style="text-transform: uppercase">
                        Tizim
                    </li>
                    <li class="nav-item">
                        <a href="{{ url('/') }}" class="nav-link">
                            <i class="nav-icon fas fa-sync"></i>
                            <p>HEMIS malumotlar</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ url('/') }}" class="nav-link">
                            <i class="nav-icon fa fa-link"></i>
                            <p>Ilmiy profillar</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ url('/') }}" class="nav-link">
                            <i class="nav-icon fa fa-cog"></i>
                            <p>Sozlamalar</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="{{ route('auth.logout') }}" class="nav-link">
                            <i class="nav-icon fa fa-power-off"></i>
                            <p>Tizimdan chiqish</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <marquee class="p-0 m-0 bg-danger">
            Sayt hozirda TEST rejimida ishlamoqda!
        </marquee>
        <section class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-12">
                        <ol class="breadcrumb float-sm-right small">
                            @foreach($breadcrumbs as $breadcrumb)
                                @if($breadcrumb['url'] == '#')
                                    <li class="breadcrumb-item active">{{ $breadcrumb['name'] }}</li>
                                @else
                                    <li class="breadcrumb-item">
                                        <a href="{{ $breadcrumb['url'] }}">{{ $breadcrumb['name'] }}</a>
                                    </li>
                                @endif
                            @endforeach
                        </ol>
                    </div>
                </div>
            </div>
        </section>

        @yield('content')
    </div>

    <footer class="main-footer">
        <div class="float-right d-none d-sm-block">
            <b>Version</b> 4.0.1
        </div>
        <strong>Qoraqalpoq davlat universiteti &copy; 2023-{{ date('Y') }}
            <a href="https://karsu.uz">KarSU KPI</a>.</strong> All rights reserved.
    </footer>
    <aside class="control-sidebar control-sidebar-dark">
    </aside>
</div>
<script src="{{ asset('plugins/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('plugins/bootstrap/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('plugins/toastr/toastr.min.js') }}"></script>
<script src="{{ asset('dist/js/adminlte.min.js') }}"></script>
<script src="{{ asset('dist/js/demo.js') }}"></script>
<script>
    $(document).ready(function () {
        // Toastr asosiy sozlamalari (Pastki o'ng burchak va 5 soniya)
        toastr.options = {
            "closeButton": true,
            "progressBar": true,
            "positionClass": "toast-bottom-right",
            "timeOut": "5000",
            "extendedTimeOut": "1000",
        };

        // Sessiyadan kelgan xabarlarni tutib olish
        @if(Session::has('success'))
        toastr.success("{{ Session::get('success') }}");
        @endif

        @if(Session::has('error'))
        toastr.error("{{ Session::get('error') }}");
        @endif

        @if(Session::has('warning'))
        toastr.warning("{{ Session::get('warning') }}");
        @endif

        @if(Session::has('info'))
        toastr.info("{{ Session::get('info') }}");
        @endif
    });
</script>
@yield('script')
</body>
</html>
