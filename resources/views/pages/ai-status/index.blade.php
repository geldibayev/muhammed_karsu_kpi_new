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
                'icon' => 'fa-hourglass-half text-success',
                'label' => 'AI kutish rejimida',
                'summary' => 'Worker faol va yangi resurs kelishi bilan tekshirishni boshlaydi.',
            ],
            'processing' => [
                'card' => 'card-warning',
                'badge' => 'badge-warning',
                'icon' => 'fa-spinner text-warning',
                'label' => 'AI navbatni ishlamoqda',
                'summary' => 'Resurslar AI tekshiruvini kutmoqda.',
            ],
            'recovering' => [
                'card' => 'card-warning',
                'badge' => 'badge-warning',
                'icon' => 'fa-sync-alt text-warning',
                'label' => 'Navbat tiklanmoqda',
                'summary' => 'Yo‘qolgan queue job avtomatik ravishda qayta navbatga qo‘yiladi.',
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

        $resourceCards = [
            [
                'value' => $resourceStatistics['evaluated'],
                'label' => 'TEKSHIRILGAN',
                'class' => 'text-success',
            ],
            [
                'value' => $resourceStatistics['waiting'],
                'label' => 'NAVBATDA',
                'class' => 'text-warning',
            ],
            [
                'value' => $resourceStatistics['failed_pending'],
                'label' => 'XATO',
                'class' => 'text-danger',
            ],
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

            <div class="card card-outline card-info">
                <div class="card-header">
                    <h2 class="card-title font-weight-bold">Worker va real queue holati</h2>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-12 col-md-4">
                            <div class="description-block border-right">
                                <h3 class="description-header {{ $status['worker_is_active'] ? 'text-success' : 'text-danger' }}">
                                    {{ $status['worker_is_active'] ? 'FAOL' : 'FAOL EMAS' }}
                                </h3>
                                <span class="description-text">WORKER</span>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="description-block border-right">
                                <h3 class="description-header text-primary">
                                    {{ $status['queue_jobs'] ?? 'N/A' }}
                                </h3>
                                <span class="description-text">REAL QUEUE JOBLARI</span>
                            </div>
                        </div>
                        <div class="col-12 col-md-4">
                            <div class="description-block">
                                <h3 class="description-header text-warning">
                                    {{ $status['processing_jobs'] ?? 'N/A' }}
                                </h3>
                                <span class="description-text">Ishlayotgan ishlar</span>
                            </div>
                        </div>
                    </div>
                    <div class="alert {{ $status['worker_is_active'] ? 'alert-success' : 'alert-warning' }} mb-0 mt-3">
                        @if($status['worker_is_active'])
                            Worker doimiy ishlamoqda. Navbat bo‘sh bo‘lsa kutadi va yangi resurs kelishi bilan avtomatik tekshiradi.
                        @else
                            Worker heartbeat aniqlanmadi. Production’da <code>ai-evaluations</code> queue workerini Supervisor orqali doimiy ishlating.
                        @endif
                        <div class="small mt-1">
                            Oxirgi worker heartbeat:
                            <strong>{{ $status['worker_heartbeat_at']?->format('d.m.Y H:i:s') ?? 'Qayd etilmagan' }}</strong>
                        </div>
                    </div>
                    @if($status['orphaned_resources'] > 0)
                        <div class="alert alert-warning mt-3 mb-0">
                            <i class="fas fa-sync-alt mr-1"></i>
                            Tizimda {{ $status['orphaned_resources'] }} ta resurs uchun navbat yozuvi qayta tiklanmoqda.
                        </div>
                    @endif
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
                        @foreach ($resourceCards as $index => $resourceCard)
                            <div class="col-12 col-md-4">
                                <div class="description-block {{ $index < 2 ? 'border-right' : '' }}">
                                    <h3 class="description-header {{ $resourceCard['class'] }}">
                                        {{ $resourceCard['value'] }}
                                    </h3>
                                    <span class="description-text">{{ $resourceCard['label'] }}</span>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    @if($resourceStatistics['legacy_untracked'] > 0)
                        <div class="text-right text-muted small mt-2">
                            Navbatga qo‘yilmagan eski resurslar: {{ $resourceStatistics['legacy_untracked'] }}
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
