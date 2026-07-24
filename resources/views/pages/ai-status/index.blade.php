@extends('layouts.app')

@section('content')
    @php
        $statusPresentation = match ($status['state']) {
            'operational' => [
                'class' => 'alert-success',
                'icon' => 'fa-check-circle',
                'label' => 'AI tekshiruvchi ishlayapti',
            ],
            'unavailable' => [
                'class' => 'alert-danger',
                'icon' => 'fa-exclamation-circle',
                'label' => 'AI tekshiruvchi ishlamayapti',
            ],
            default => [
                'class' => 'alert-secondary',
                'icon' => 'fa-question-circle',
                'label' => 'AI tekshiruvchi statusi hali aniqlanmagan',
            ],
        };
    @endphp

    <section class="content">
        <div class="container-fluid">
            <div class="alert {{ $statusPresentation['class'] }} d-flex align-items-center" role="status">
                <i class="fas {{ $statusPresentation['icon'] }} fa-2x mr-3" aria-hidden="true"></i>
                <div>
                    <h1 class="h5 font-weight-bold mb-1">{{ $statusPresentation['label'] }}</h1>
                    <div class="small">
                        @if($status['checked_at'])
                            Oxirgi tekshiruv: {{ $status['checked_at']->format('d.m.Y H:i:s') }}
                        @else
                            AI tekshiruvlari bo‘yicha audit yozuvi hali mavjud emas.
                        @endif
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-lg-4 col-md-6">
                    <div class="small-box bg-info">
                        <div class="inner">
                            <h3>{{ $statistics['total_checks'] }}</h3>
                            <p>Umumiy AI tekshiruvlari</p>
                        </div>
                        <div class="icon"><i class="fas fa-robot"></i></div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="small-box bg-success">
                        <div class="inner">
                            <h3>{{ $statistics['successful_checks'] }}</h3>
                            <p>Muvaffaqiyatli tekshiruvlar</p>
                        </div>
                        <div class="icon"><i class="fas fa-check"></i></div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="small-box bg-danger">
                        <div class="inner">
                            <h3>{{ $statistics['failed_checks'] }}</h3>
                            <p>Xato yakunlangan tekshiruvlar</p>
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
