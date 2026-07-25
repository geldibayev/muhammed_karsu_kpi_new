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
            <div class="card card-outline card-primary mb-4">
                <div class="card-body d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                        <div class="text-muted text-uppercase small font-weight-bold">Barcha holatlar bo‘yicha</div>
                        <h2 class="mb-0 font-weight-bold">Jami resurslar</h2>
                    </div>
                    <div class="display-4 font-weight-bold text-primary">
                        {{ number_format($statistics['total'], 0, '.', ' ') }}
                    </div>
                </div>
            </div>

            <div class="row">
                @foreach($statistics['statuses'] as $statusStatistic)
                    @php
                        $presentation = $presentations[$statusStatistic['value']];
                    @endphp
                    <div class="col-xl col-md-6">
                        <div class="small-box {{ $presentation['background'] }}">
                            <div class="inner">
                                <h3>{{ number_format($statusStatistic['count'], 0, '.', ' ') }}</h3>
                                <p class="font-weight-bold mb-1">{{ $statusStatistic['label'] }}</p>
                                <div class="small">{{ $statusStatistic['description'] }}</div>
                            </div>
                            <div class="icon">
                                <i class="fas {{ $presentation['icon'] }}" aria-hidden="true"></i>
                            </div>
                            <div class="small-box-footer">
                                Jami resurslarning {{ number_format($statusStatistic['percentage'], 1) }}%
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>
@endsection
