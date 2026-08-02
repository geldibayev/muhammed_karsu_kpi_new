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
            'idle' => [
                'card' => 'card-success',
                'badge' => 'badge-success',
                'icon' => 'fa-check-circle text-success',
                'label' => 'Navbat bo‘sh',
                'summary' => 'Barcha yuborilgan resurslar ko‘rib chiqilgan.',
            ],
            'processing' => [
                'card' => 'card-warning',
                'badge' => 'badge-warning',
                'icon' => 'fa-spinner text-warning',
                'label' => 'Tekshiruv davom etmoqda',
                'summary' => 'Navbatdagi resurslar avtomatik tekshirilmoqda.',
            ],
            'recovering' => [
                'card' => 'card-warning',
                'badge' => 'badge-warning',
                'icon' => 'fa-sync-alt text-warning',
                'label' => 'Navbat qayta tiklanmoqda',
                'summary' => 'Uzilib qolgan vazifa avtomatik ravishda qayta yuboriladi.',
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
                'label' => 'AI o‘chirilgan',
                'summary' => 'AI tekshiruvi administrator tomonidan vaqtincha to‘xtatilgan.',
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

        $summaryCards = [
            [
                'value' => $resourceStatistics['total'],
                'label' => 'JAMI RESURSLAR',
                'icon' => 'fa-folder-open',
                'class' => 'info-box-icon bg-info',
            ],
            [
                'value' => $resourceStatistics['evaluated'],
                'label' => 'AI TEKSHIRGAN',
                'icon' => 'fa-check-double',
                'class' => 'info-box-icon bg-success',
            ],
            [
                'value' => $resourceStatistics['waiting'],
                'label' => 'NAVBATDA',
                'icon' => 'fa-clock',
                'class' => 'info-box-icon bg-warning',
            ],
            [
                'value' => $resourceStatistics['failed_pending'] + $resourceStatistics['legacy_untracked'],
                'label' => 'E’TIBOR TALAB QILADI',
                'icon' => 'fa-exclamation-triangle',
                'class' => 'info-box-icon bg-danger',
            ],
        ];

        $detailCards = [
            ['value' => $resourceStatistics['accepted'], 'label' => 'QABUL QILINGAN', 'class' => 'text-success'],
            ['value' => $resourceStatistics['cancelled'], 'label' => 'RAD ETILGAN', 'class' => 'text-danger'],
            ['value' => $resourceStatistics['human_review'], 'label' => 'INSON TEKSHIRUVIDA', 'class' => 'text-info'],
            ['value' => $resourceStatistics['failed_pending'], 'label' => 'AI XATOSI', 'class' => 'text-warning'],
        ];
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
                    <h2 class="card-title font-weight-bold">AI tekshiruv statistikasi</h2>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach ($summaryCards as $summaryCard)
                            <div class="col-12 col-sm-6 col-xl-3">
                                <div class="info-box shadow-none border">
                                    <span class="{{ $summaryCard['class'] }}">
                                        <i class="fas {{ $summaryCard['icon'] }}"></i>
                                    </span>
                                    <div class="info-box-content">
                                        <span class="info-box-text">{{ $summaryCard['label'] }}</span>
                                        <span class="info-box-number h4 mb-0">{{ number_format($summaryCard['value']) }}</span>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="row text-center border-top pt-3 mt-1">
                        @foreach ($detailCards as $index => $detailCard)
                            <div class="col-6 col-lg-3">
                                <div class="description-block {{ $index < 3 ? 'border-right' : '' }}">
                                    <h3 class="description-header {{ $detailCard['class'] }}">
                                        {{ number_format($detailCard['value']) }}
                                    </h3>
                                    <span class="description-text">{{ $detailCard['label'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($resourceStatistics['legacy_untracked'] > 0)
                        <div class="alert alert-light border mt-3 mb-0">
                            <i class="fas fa-info-circle text-muted mr-1"></i>
                            Navbat tarixi mavjud bo‘lmagan eski resurslar:
                            <strong>{{ number_format($resourceStatistics['legacy_untracked']) }}</strong>
                        </div>
                    @endif

                    @if($resourceStatistics['waiting'] > 0 && $status['oldest_waiting_at'] !== null)
                        <div class="small text-muted mt-3">
                            Eng uzoq kutayotgan resurs navbatga qo‘yilgan vaqt:
                            <strong>{{ $status['oldest_waiting_at']->format('d.m.Y H:i:s') }}</strong>
                        </div>
                    @endif

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
