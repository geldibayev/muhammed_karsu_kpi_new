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
            @php
                $user = $layoutUser;
                $degreeName = $layoutWorkplace?->academic_degree?->name;
                $rankName = $layoutWorkplace?->academic_rank?->name;
                $avatarUrl = $user->image_url;
            @endphp
            <li class="nav-item dropdown">
                <a class="nav-link d-flex align-items-center text-right py-1" data-toggle="dropdown" href="#"
                   role="button" aria-haspopup="true" aria-expanded="false">
                    <span class="d-inline-flex align-items-center justify-content-center h5 mb-0 mr-3
                                 font-weight-bold text-success text-nowrap">
                        {{ number_format((float) $layoutTotalPoints, 2) }} / 100 ball
                    </span>
                    @if($avatarUrl)
                        <img src="{{ $avatarUrl }}"
                             alt="{{ $user->full ?: ($user->short ?: 'Foydalanuvchi') }}"
                             class="img-circle elevation-1 img-size-32 mr-2 flex-shrink-0"
                             style="object-fit: cover"
                             data-rating-avatar-image>
                    @endif
                    <span data-rating-avatar-fallback
                          role="img"
                          aria-label="{{ $user->full ?: ($user->short ?: 'Foydalanuvchi') }}"
                          class="{{ $avatarUrl ? 'd-none' : 'd-inline-flex' }} img-size-32 img-circle bg-light border text-secondary
                              align-items-center justify-content-center flex-shrink-0 mr-2">
                        <i class="fas fa-user" aria-hidden="true"></i>
                    </span>
                    <span class="d-flex flex-column align-items-end lh-sm">
                        <span class="font-weight-bold">
                            {{ trim(($degreeName ?? '') . '., ' . $user->short) ?: 'Foydalanuvchi' }}
                        </span>
                        <span class="small text-muted">
                            {{ $rankName ?? 'Ilmiy unvon kiritilmagan' }}
                        </span>
                    </span>
                </a>
                <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right" style="left: inherit; right: 0;">
                    @if(count(auth()->user()->rol) > 1)
                        <span class="dropdown-item dropdown-header">Foydalanuvchi rollari</span>
                        <div class="dropdown-divider"></div>
                        @if(in_array('super_admin', auth()->user()->rol))
                            <a href="#" class="dropdown-item small">
                                Super admin
                            </a>
                        @endif
                        @if(in_array('moder', auth()->user()->rol))
                            <a href="#" class="dropdown-item small">
                                Tekshiruvchi
                            </a>
                        @endif
                        @if(in_array('dean', auth()->user()->rol))
                            <a href="#" class="dropdown-item small">
                                Dekan
                            </a>
                        @endif
                        @if(in_array('department', auth()->user()->rol))
                            <a href="#" class="dropdown-item small">
                                Kafedra mudiri
                            </a>
                        @endif
                        @if(in_array('teacher', auth()->user()->rol))
                            <a href="#" class="dropdown-item small">
                                O‘qituvchi
                            </a>
                        @endif
                        <div class="dropdown-divider"></div>
                    @endif
                    <a href="{{ route('profile') }}" class="dropdown-item small">
                        Mening profilim
                    </a>
                    @can('access-manual-reviews')
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('reviews.index') }}" class="dropdown-item small font-weight-bold text-primary">
                            <i class="fas fa-clipboard-check mr-2"></i> Baholash
                        </a>
                    @endcan
                    @can('access-ai-human-reviews')
                        <div class="dropdown-divider"></div>
                        <a href="{{ route('ai-human-reviews.index') }}" class="dropdown-item small font-weight-bold text-info">
                            <i class="fas fa-user-check mr-2"></i> AI inson tekshiruvi
                        </a>
                    @endcan
                </div>
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
                    @can('view-ratings')
                        <li class="nav-item">
                            <a href="{{ route('ratings.index') }}"
                               class="nav-link @if(request()->routeIs('ratings.*')) active @endif">
                                <i class="nav-icon fas fa-trophy"></i>
                                <p>Reyting</p>
                            </a>
                        </li>
                    @endcan
                    @can('view-resource-statistics')
                        <li class="nav-item">
                            <a href="{{ route('criterion-resource-statistics.index') }}"
                               class="nav-link @if(request()->routeIs('criterion-resource-statistics.*')) active @endif">
                                <i class="nav-icon fas fa-table"></i>
                                <p>Kriteriyalar statistikasi</p>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('statistics.index') }}"
                               class="nav-link @if(request()->routeIs('statistics.*')) active @endif">
                                <i class="nav-icon fas fa-chart-pie"></i>
                                <p>Statistika</p>
                            </a>
                        </li>
                    @endcan
                    <li class="nav-item @if(request()->routeIs('files.show', 'upload.details') || request()->is('home/files*')) menu-open @endif">
                        <a href="#"
                           class="nav-link @if(request()->routeIs('files.show', 'upload.details') || request()->is('home/files*')) active @endif">
                            <i class="nav-icon fa fa-layer-group"></i>
                            <p>
                                Resurslar
                                <i class="right fas fa-angle-left"></i>
                            </p>
                        </a>
                        <ul class="nav nav-treeview">
                            <li class="nav-item">
                                <a href="{{ route('files.show', 'received') }}"
                                   class="nav-link @if(request()->url() == route('files.show', 'received') || (($status ?? null) === \App\Enums\DatumStatus::Received)) active @endif">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Yuborilgan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('files.show', 'checking') }}"
                                   class="nav-link @if(request()->url() == route('files.show', 'checking') || (($status ?? null) === \App\Enums\DatumStatus::Checking)) active @endif">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Tekshirilmoqda</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('files.show', 'accepted') }}"
                                   class="nav-link @if(request()->url() == route('files.show', 'accepted') || (($status ?? null) === \App\Enums\DatumStatus::Accepted)) active @endif">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Tasdiqlangan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('files.show', 'cancelled') }}"
                                   class="nav-link @if(request()->url() == route('files.show', 'cancelled') || (($status ?? null) === \App\Enums\DatumStatus::Cancelled)) active @endif">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>Qaytarilgan</p>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a href="{{ route('files.show', 'deleted') }}"
                                   class="nav-link @if(request()->url() == route('files.show', 'deleted') || (($status ?? null) === \App\Enums\DatumStatus::Deleted)) active @endif">
                                    <i class="far fa-circle nav-icon"></i>
                                    <p>O‘chirilgan</p>
                                </a>
                            </li>
                        </ul>
                    </li>
                    @can('access-manual-reviews')
                        <li class="nav-item">
                            <a href="{{ route('reviews.index') }}"
                               class="nav-link @if(request()->routeIs('reviews.*') && (($reviewQueue ?? 'manual') !== 'ai')) active @endif">
                                <i class="nav-icon fas fa-clipboard-check"></i>
                                <p>Baholash</p>
                            </a>
                        </li>
                    @endcan
                    @can('access-ai-human-reviews')
                        <li class="nav-item">
                            <a href="{{ route('ai-human-reviews.index') }}"
                               class="nav-link @if(request()->routeIs('ai-human-reviews.*') || (($reviewQueue ?? null) === 'ai')) active @endif">
                                <i class="nav-icon fas fa-user-check"></i>
                                <p>AI inson tekshiruvi</p>
                            </a>
                        </li>
                    @endcan
                    <li class="nav-header font-weight-bold" style="text-transform: uppercase">
                        Tizim
                    </li>
                    @can('export-employment-data')
                        <li class="nav-item">
                            <a href="{{ route('users.external-part-timers.index') }}"
                               class="nav-link @if(request()->routeIs('users.external-part-timers.*')) active @endif">
                                <i class="nav-icon fas fa-user-tie"></i>
                                <p>Tashqi o‘rindoshlar</p>
                            </a>
                        </li>
                    @endcan
                    @can('view-ai-status')
                        @php
                            $aiMenuPresentation = match ($aiMenuStatus['state'] ?? 'unknown') {
                                'operational' => [
                                    'badge' => 'badge-success',
                                    'icon' => 'fa-check-circle',
                                    'label' => 'Ishlayapti',
                                ],
                                'idle' => [
                                    'badge' => 'badge-success',
                                    'icon' => 'fa-hourglass-half',
                                    'label' => 'Kutmoqda',
                                ],
                                'processing' => [
                                    'badge' => 'badge-warning',
                                    'icon' => 'fa-spinner',
                                    'label' => 'Navbatda',
                                ],
                                'recovering' => [
                                    'badge' => 'badge-warning',
                                    'icon' => 'fa-sync-alt',
                                    'label' => 'Tiklanmoqda',
                                ],
                                'degraded' => [
                                    'badge' => 'badge-warning',
                                    'icon' => 'fa-exclamation-triangle',
                                    'label' => 'E’tibor kerak',
                                ],
                                'unavailable' => [
                                    'badge' => 'badge-danger',
                                    'icon' => 'fa-times-circle',
                                    'label' => 'Ishlamayapti',
                                ],
                                'disabled' => [
                                    'badge' => 'badge-secondary',
                                    'icon' => 'fa-pause-circle',
                                    'label' => 'O‘chirilgan',
                                ],
                                default => [
                                    'badge' => 'badge-secondary',
                                    'icon' => 'fa-question-circle',
                                    'label' => 'Aniqlanmagan',
                                ],
                            };
                            $aiMenuTitle = $aiMenuStatus['reason']
                                ?? (($aiMenuStatus['pending_resources'] ?? 0).' ta resurs navbatda');
                        @endphp
                        <li class="nav-item">
                            <a href="{{ route('ai-status.index') }}"
                               title="{{ $aiMenuTitle }}"
                               class="nav-link @if(request()->routeIs('ai-status.*')) active @endif">
                                <i class="nav-icon fas fa-robot"></i>
                                <p>
                                    AI holati
                                    <span class="right badge {{ $aiMenuPresentation['badge'] }}">
                                        <i class="fas {{ $aiMenuPresentation['icon'] }} mr-1" aria-hidden="true"></i>
                                        {{ $aiMenuPresentation['label'] }}
                                    </span>
                                </p>
                            </a>
                        </li>
                    @endcan
                    @can('view-ai-human-reviewer-statistics')
                        <li class="nav-item">
                            <a href="{{ route('ai-human-reviewer-statistics.index') }}"
                               class="nav-link @if(request()->routeIs('ai-human-reviewer-statistics.*')) active @endif">
                                <i class="nav-icon fas fa-users-cog"></i>
                                <p>AI mas’ullar statistikasi</p>
                            </a>
                        </li>
                    @endcan
                    <li class="nav-item">
                        <a href="{{ route('reviewer-assignments.index') }}"
                           class="nav-link @if(request()->routeIs('reviewer-assignments.*')) active @endif">
                            <i class="nav-icon fas fa-user-shield"></i>
                            <p>Ma’sullar</p>
                        </a>
                    </li>
                    {{--<li class="nav-item">
                        <a href="{{ url('/') }}" class="nav-link">
                            <i class="nav-icon fas fa-sync"></i>
                            <p>HEMIS malumotlar</p>
                        </a>
                    </li>--}}
                    @can('manage-kpi-settings')
                        <li class="nav-item">
                            <a href="{{ route('settings.index') }}"
                               class="nav-link @if(request()->routeIs('settings.*')) active @endif">
                                <i class="nav-icon fa fa-cog"></i>
                                <p>Sozlamalar</p>
                            </a>
                        </li>
                    @endcan
                    <li class="nav-item">
                        <form method="POST" action="{{ route('auth.logout') }}">
                            @csrf
                            <button type="submit" class="nav-link btn btn-link text-left w-100">
                                <i class="nav-icon fa fa-power-off"></i>
                                <p>Tizimdan chiqish</p>
                            </button>
                        </form>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <div class="content-wrapper">
        <marquee class="p-0 m-0 bg-danger">
            Sayt hozirda TEST rejimida ishlamoqda!
        </marquee>
        <x-resource-upload-deadline
            :deadline="$layoutResourceUploadDeadline"
            :deadline-label="$layoutResourceUploadDeadlineLabel"
            :is-open="$layoutResourceUploadWindowOpen"
        />
        @if($layoutUser->isUploadBlocked())
            <div class="alert alert-danger rounded-0 mb-0" role="alert">
                <div class="container-fluid">
                    <i class="fas fa-ban mr-1" aria-hidden="true"></i>
                    <strong>Resurs yuklash bloklangan.</strong>
                    {{ $layoutUser->upload_block_reason }}
                </div>
            </div>
        @endif
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
<script src="{{ asset('dist/js/rating-avatar-fallback.js') }}"></script>
<x-session-notifications />
@yield('script')
</body>
</html>
