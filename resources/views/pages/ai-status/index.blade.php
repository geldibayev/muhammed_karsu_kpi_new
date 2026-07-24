@extends('layouts.app')

@section('content')
    @php
        $statusPresentation = match ($status['state']) {
            'operational' => [
                'class' => 'alert-success',
                'icon' => 'fa-check-circle',
                'label' => 'AI tekshiruvchi ishlayapti',
                'description' => 'Oxirgi AI urinish muvaffaqiyatli yakunlangan.',
            ],
            'processing' => [
                'class' => 'alert-warning',
                'icon' => 'fa-spinner',
                'label' => 'AI resurslari navbatda',
                'description' => 'Yangi resurslar AI tekshiruvini kutmoqda.',
            ],
            'degraded' => [
                'class' => 'alert-warning',
                'icon' => 'fa-exclamation-triangle',
                'label' => 'AI ishlayapti, lekin e’tibor kerak',
                'description' => 'Ayrim resurslarda AI xatosi qayd etilgan.',
            ],
            'unavailable' => [
                'class' => 'alert-danger',
                'icon' => 'fa-exclamation-circle',
                'label' => 'AI tekshiruvchi ishlamayapti',
                'description' => 'Oxirgi urinish yoki tekshiruv navbatida muammo aniqlandi.',
            ],
            default => [
                'class' => 'alert-secondary',
                'icon' => 'fa-question-circle',
                'label' => 'AI tekshiruvchi statusi hali aniqlanmagan',
                'description' => 'AI tekshiruvlari bo‘yicha audit yozuvi yoki navbat hali mavjud emas.',
            ],
        };
    @endphp

    <section class="content">
        <div class="container-fluid">
            <div class="alert {{ $statusPresentation['class'] }} shadow-sm" role="status">
                <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                    <div class="d-flex align-items-center">
                        <i class="fas {{ $statusPresentation['icon'] }} fa-3x mr-3" aria-hidden="true"></i>
                        <div>
                            <h1 class="h4 font-weight-bold mb-1">{{ $statusPresentation['label'] }}</h1>
                            <div>{{ $statusPresentation['description'] }}</div>
                        </div>
                    </div>
                    <div class="text-md-right mt-3 mt-md-0">
                        <div class="font-weight-bold">
                            {{ $status['pending_resources'] }} ta resurs jarayonda
                        </div>
                        <div class="small">
                            @if($status['checked_at'])
                                Oxirgi AI urinish: {{ $status['checked_at']->format('d.m.Y H:i:s') }}
                            @else
                                AI urinish vaqti hali mavjud emas
                            @endif
                        </div>
                    </div>
                </div>

                @if($status['reason'])
                    <hr>
                    <div class="d-flex align-items-start">
                        <i class="fas fa-info-circle mt-1 mr-2" aria-hidden="true"></i>
                        <div>
                            <strong>
                                {{ $status['state'] === 'unavailable' ? 'Muammo sababi:' : 'Holat izohi:' }}
                            </strong>
                            {{ $status['reason'] }}
                        </div>
                    </div>
                @endif
            </div>

            <div class="card card-outline card-primary">
                <div class="card-header d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                    <div>
                        <h2 class="card-title font-weight-bold">AI kriteriyalaridagi resurslar</h2>
                        <div class="small text-muted">
                            Barcha davrlar bo‘yicha faqat AI tekshiradigan kriteriyalar hisoblangan.
                        </div>
                    </div>
                    <div class="small text-muted mt-2 mt-md-0">
                        Oxirgi AI resursi:
                        <strong>
                            {{ $resourceStatistics['last_submission_at']?->format('d.m.Y H:i:s') ?? 'Hali mavjud emas' }}
                        </strong>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-xl-3 col-md-6">
                            <div class="info-box bg-info">
                                <span class="info-box-icon"><i class="fas fa-folder-open"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Jami AI resurslari</span>
                                    <span class="info-box-number">{{ $resourceStatistics['total'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="info-box bg-success">
                                <span class="info-box-icon"><i class="fas fa-robot"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">AI tekshirgan</span>
                                    <span class="info-box-number">{{ $resourceStatistics['evaluated'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="info-box bg-warning">
                                <span class="info-box-icon"><i class="fas fa-hourglass-half"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Tekshiruvni kutmoqda</span>
                                    <span class="info-box-number">{{ $resourceStatistics['waiting'] }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-3 col-md-6">
                            <div class="info-box bg-danger">
                                <span class="info-box-icon"><i class="fas fa-exclamation-triangle"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">AI xatosi sabab kutilmoqda</span>
                                    <span class="info-box-number">{{ $resourceStatistics['failed_pending'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-light border mb-4">
                        <i class="fas fa-info-circle text-primary mr-1" aria-hidden="true"></i>
                        <strong>Hisoblash tartibi:</strong>
                        eski qabul qilingan yoki qaytarilgan AI resurslari tarix yozuvi bo‘lmasa ham
                        “AI tekshirgan” soniga kiradi. Yangi yuklangan va hali AI natijasi yo‘q resurs
                        “Tekshiruvni kutmoqda” sifatida ko‘rsatiladi.
                    </div>

                    <div class="row">
                        <div class="col-lg-3 col-sm-6">
                            <div class="description-block border-right">
                                <h3 class="description-header text-success">{{ $resourceStatistics['accepted'] }}</h3>
                                <span class="description-text">QABUL QILINGAN</span>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="description-block border-right">
                                <h3 class="description-header text-danger">{{ $resourceStatistics['cancelled'] }}</h3>
                                <span class="description-text">QAYTARILGAN</span>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="description-block border-right">
                                <h3 class="description-header text-warning">{{ $resourceStatistics['human_review'] }}</h3>
                                <span class="description-text">INSON KO‘RIGI KERAK</span>
                            </div>
                        </div>
                        <div class="col-lg-3 col-sm-6">
                            <div class="description-block">
                                <h3 class="description-header">{{ number_format($resourceStatistics['evaluation_rate'], 1) }}%</h3>
                                <span class="description-text">AI TEKSHIRGAN ULUSH</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <h2 class="h5 font-weight-bold mb-1">AI xizmatining urinishlari</h2>
            <p class="text-muted small">
                Bu raqamlar resurslar soni emas, AI xizmatiga qilingan tekshiruv urinishlari soni.
            </p>
            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{{ $statistics['total_checks'] }}</h3>
                            <p>Jami AI urinishlari</p>
                        </div>
                        <div class="icon"><i class="fas fa-robot"></i></div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>{{ $statistics['successful_checks'] }}</h3>
                            <p>Natija qaytargan urinishlar</p>
                        </div>
                        <div class="icon"><i class="fas fa-check"></i></div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>{{ $statistics['failed_checks'] }}</h3>
                            <p>Xato bo‘lgan urinishlar</p>
                        </div>
                        <div class="icon"><i class="fas fa-times"></i></div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Oxirgi muvaffaqiyatli tekshiruv</span>
                            <span class="info-box-number">
                                {{ $statistics['last_success_at']?->format('d.m.Y H:i:s') ?? 'Hali mavjud emas' }}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-box">
                        <span class="info-box-icon bg-danger"><i class="fas fa-clock"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">Oxirgi xato</span>
                            <span class="info-box-number">
                                {{ $statistics['last_failure_at']?->format('d.m.Y H:i:s') ?? 'Hali mavjud emas' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-info">
                <div class="card-header">
                    <h2 class="card-title font-weight-bold">Hisobotlar kesimida AI holati</h2>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover table-striped small mb-0">
                            <thead>
                            <tr>
                                <th>Hisobot davri</th>
                                <th class="text-center">Jami</th>
                                <th class="text-center">AI tekshirgan</th>
                                <th class="text-center">Navbatda</th>
                                <th class="text-center">AI xatosi</th>
                                <th class="text-center">Qabul qilingan</th>
                                <th class="text-center">Qaytarilgan</th>
                                <th class="text-center">Bajarilish</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($reportStatistics as $reportStatistic)
                                <tr>
                                    <td class="align-middle">
                                        <span class="font-weight-bold">{{ $reportStatistic['name'] }}</span>
                                        @if($reportStatistic['active'])
                                            <span class="badge badge-success ml-1">Faol</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-center">{{ $reportStatistic['total'] }}</td>
                                    <td class="align-middle text-center text-success font-weight-bold">
                                        {{ $reportStatistic['evaluated'] }}
                                    </td>
                                    <td class="align-middle text-center text-warning font-weight-bold">
                                        {{ $reportStatistic['waiting'] }}
                                    </td>
                                    <td class="align-middle text-center text-danger font-weight-bold">
                                        {{ $reportStatistic['failed_pending'] }}
                                    </td>
                                    <td class="align-middle text-center">{{ $reportStatistic['accepted'] }}</td>
                                    <td class="align-middle text-center">{{ $reportStatistic['cancelled'] }}</td>
                                    <td class="align-middle text-center">
                                        <span class="badge badge-info px-2 py-1">
                                            {{ number_format($reportStatistic['evaluation_rate'], 1) }}%
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        AI kriteriyalariga tegishli resurslar hali mavjud emas.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h2 class="card-title font-weight-bold">Oxirgi 3 ta AI tekshiruvi</h2>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover small mb-0">
                            <thead>
                            <tr>
                                <th>Vaqt</th>
                                <th>Foydalanuvchi</th>
                                <th>Kriteriya</th>
                                <th>Natija</th>
                                <th class="text-center">Ball</th>
                                <th>Izoh</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($recentChecks as $check)
                                @php
                                    $resultPresentation = $check->message_type === 'ai_failed'
                                        ? ['class' => 'badge-danger', 'label' => 'AI xatosi']
                                        : match ($check->type) {
                                            'success' => ['class' => 'badge-success', 'label' => 'Qabul qilindi'],
                                            'error' => ['class' => 'badge-danger', 'label' => 'Qaytarildi'],
                                            default => ['class' => 'badge-warning', 'label' => 'Tekshiruvda'],
                                        };
                                @endphp
                                <tr>
                                    <td class="align-middle text-nowrap">
                                        {{ $check->created_at?->format('d.m.Y H:i:s') ?? '—' }}
                                    </td>
                                    <td class="align-middle">
                                        {{ $check->datum?->user?->full ?: 'Noma’lum' }}
                                        @if($check->datum?->user?->hemis_id)
                                            <div class="text-muted">HEMIS: {{ $check->datum->user->hemis_id }}</div>
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        {{ data_get($check->datum?->criterion?->name, 'uz', 'Nomsiz kriteriya') }}
                                    </td>
                                    <td class="align-middle">
                                        <span class="badge {{ $resultPresentation['class'] }}">
                                            {{ $resultPresentation['label'] }}
                                        </span>
                                    </td>
                                    <td class="align-middle text-center">
                                        {{ $check->message_type === 'ai_evaluation'
                                            ? number_format((float) ($check->datum?->point ?? 0), 2)
                                            : '—' }}
                                    </td>
                                    <td class="align-middle text-break">{{ $check->message ?: 'Izoh mavjud emas' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        AI tekshiruvlari hali amalga oshirilmagan.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
