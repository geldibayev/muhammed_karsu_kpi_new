@extends('layouts.app')

@section('content')
    @php
        $presentations = [
            'received' => ['background' => 'bg-info', 'icon' => 'fa-inbox'],
            'checking' => ['background' => 'bg-warning', 'icon' => 'fa-hourglass-half'],
            'accepted' => ['background' => 'bg-success', 'icon' => 'fa-check-circle'],
            'cancelled' => ['background' => 'bg-danger', 'icon' => 'fa-undo-alt'],
            'deleted' => ['background' => 'bg-secondary', 'icon' => 'fa-trash-alt'],
        ];
    @endphp

    <section class="content">
        <div class="container-fluid">
            <div class="info-box shadow-sm mb-4">
                <span class="info-box-icon bg-primary">
                    <i class="fas fa-layer-group" aria-hidden="true"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text text-muted text-uppercase font-weight-bold">
                        Barcha holatlar bo‘yicha
                    </span>
                    <div class="d-flex align-items-center justify-content-between flex-wrap">
                        <span class="info-box-number h4 mb-0">Jami resurslar</span>
                        <span class="h2 text-primary font-weight-bold mb-0">
                            {{ number_format($statistics['total'], 0, '.', ' ') }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center justify-content-between mb-2">
                <h5 class="font-weight-bold mb-0">Holatlar kesimida</h5>
                <span class="text-muted small">5 ta holat</span>
            </div>

            <div class="row">
                @foreach($statistics['statuses'] as $statusStatistic)
                    @php
                        $presentation = $presentations[$statusStatistic['value']];
                    @endphp
                    <div class="col-xl-4 col-md-6 d-flex">
                        <div class="info-box shadow-sm flex-fill">
                            <span class="info-box-icon {{ $presentation['background'] }}">
                                <i class="fas {{ $presentation['icon'] }}" aria-hidden="true"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text font-weight-bold">{{ $statusStatistic['label'] }}</span>
                                <div class="d-flex align-items-center justify-content-between">
                                    <span class="info-box-number h4 mb-0">
                                        {{ number_format($statusStatistic['count'], 0, '.', ' ') }}
                                    </span>
                                    <span class="badge badge-light border">
                                        {{ number_format($statusStatistic['percentage'], 1) }}%
                                    </span>
                                </div>
                                <span class="text-muted small mt-1">
                                    {{ $statusStatistic['description'] }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
