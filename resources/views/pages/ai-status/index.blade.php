@extends('layouts.app')

@section('content')
    @php
        $statusPresentation = match ($status['state']) {
            'operational' => [
                'card' => 'card-success',
                'badge' => 'badge-success',
                'icon' => 'fa-check-circle text-success',
                'label' => 'AI ishlayapti',
                'summary' => 'Oxirgi tekshiruv muvaffaqiyatli yakunlangan.',
            ],
            'processing' => [
                'card' => 'card-warning',
                'badge' => 'badge-warning',
                'icon' => 'fa-spinner text-warning',
                'label' => 'AI navbatni ishlamoqda',
                'summary' => 'Resurslar AI tekshiruvini kutmoqda.',
            ],
            'degraded' => [
                'card' => 'card-warning',
                'badge' => 'badge-warning',
                'icon' => 'fa-exclamation-triangle text-warning',
                'label' => 'AI ishlayapti, ammo xatolar bor',
                'summary' => 'Ayrim resurslar bo‘yicha AI xatosi qayd etilgan.',
            ],
            'unavailable' => [
                'card' => 'card-danger',
                'badge' => 'badge-danger',
                'icon' => 'fa-times-circle text-danger',
                'label' => 'AI ishlamayapti',
                'summary' => 'Oxirgi AI urinishida muammo aniqlandi.',
            ],
            'disabled' => [
                'card' => 'card-secondary',
                'badge' => 'badge-secondary',
                'icon' => 'fa-pause-circle text-secondary',
                'label' => 'AI oвЂchirilgan',
                'summary' => 'AI tekshiruvi administrator tomonidan vaqtincha toвЂxtatilgan.',
            ],
            default => [
                'card' => 'card-secondary',
                'badge' => 'badge-secondary',
                'icon' => 'fa-question-circle text-secondary',
                'label' => 'AI holati aniqlanmagan',
                'summary' => 'AI hali tekshiruv natijasini qaytarmagan.',
            ],
        };
        $messageHeading = match ($status['last_message_type']) {
            'failure' => 'Oxirgi AI xabari',
            'success' => 'Oxirgi tekshiruv',
            default => 'Joriy holat',
        };
    @endphp

    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline {{ $statusPresentation['card'] }} shadow-sm">
                <div class="card-body p-4">
                    <div class="d-flex flex-column flex-md-row align-items-md-start">
                        <i class="fas {{ $statusPresentation['icon'] }} fa-3x mr-md-4 mb-3 mb-md-0"
                           aria-hidden="true"></i>
                        <div class="flex-grow-1">
                            <span class="badge {{ $statusPresentation['badge'] }} px-3 py-2 mb-2">
                                {{ $statusPresentation['label'] }}
                            </span>
                            <p class="text-muted mb-3">{{ $statusPresentation['summary'] }}</p>

                            <div class="bg-light border rounded p-3">
                                <div class="small font-weight-bold text-uppercase text-muted mb-1">
                                    {{ $messageHeading }}
                                </div>
                                <div class="h5 font-weight-bold mb-2">
                                    {{ $status['last_message'] ?? 'AI xabari hali mavjud emas.' }}
                                </div>
                                <div class="small text-muted">
                                    {{ $status['last_message_at']?->format('d.m.Y H:i:s') ?? 'Vaqt qayd etilmagan' }}
                                </div>
                                @if($status['last_message_datum_id'] !== null)
                                    <div class="small text-muted mt-1">
                                        Hujjat ID: <strong>{{ $status['last_message_datum_id'] }}</strong>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h2 class="card-title font-weight-bold">Resurslar holati</h2>
                    <div class="card-tools text-muted">
                        Jami: <strong>{{ $resourceStatistics['total'] }}</strong>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 col-lg-3">
                            <div class="description-block border-right">
                                <h3 class="description-header text-success">
                                    {{ $resourceStatistics['evaluated'] }}
                                </h3>
                                <span class="description-text">TEKSHIRILGAN</span>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="description-block border-right">
                                <h3 class="description-header text-warning">
                                    {{ $resourceStatistics['waiting'] }}
                                </h3>
                                <span class="description-text">NAVBATDA</span>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="description-block border-right">
                                <h3 class="description-header text-danger">
                                    {{ $resourceStatistics['failed_pending'] }}
                                </h3>
                                <span class="description-text">XATO</span>
                            </div>
                        </div>
                        <div class="col-6 col-lg-3">
                            <div class="description-block">
                                <h3 class="description-header text-secondary">
                                    {{ $resourceStatistics['legacy_untracked'] }}
                                </h3>
                                <span class="description-text">NAVBATGA QO‘YILMAGAN</span>
                            </div>
                        </div>
                    </div>

                    <div class="progress progress-sm mt-3" aria-label="AI tekshirgan resurslar ulushi">
                        <div class="progress-bar bg-success"
                             role="progressbar"
                             style="width: {{ $resourceStatistics['evaluation_rate'] }}%"
                             aria-valuenow="{{ $resourceStatistics['evaluation_rate'] }}"
                             aria-valuemin="0"
                             aria-valuemax="100">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between small text-muted mt-2">
                        <span>AI tekshirgan ulush</span>
                        <strong>{{ number_format($resourceStatistics['evaluation_rate'], 1) }}%</strong>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
