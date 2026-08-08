@extends('layouts.app')

@section('content')
    @php($summary = $statistics['summary'])

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                @foreach([
                    ['label' => 'Mas’ullar', 'value' => $summary['reviewers'], 'icon' => 'fa-users', 'color' => 'info'],
                    ['label' => 'Jami biriktirilgan', 'value' => $summary['total'], 'icon' => 'fa-tasks', 'color' => 'primary'],
                    ['label' => 'Tekshirilgan', 'value' => $summary['checked'], 'icon' => 'fa-clipboard-check', 'color' => 'info'],
                    ['label' => 'Tekshirilmagan', 'value' => $summary['unchecked'], 'icon' => 'fa-hourglass-half', 'color' => 'warning'],
                    ['label' => 'Qabul qilingan', 'value' => $summary['approved'], 'icon' => 'fa-check-circle', 'color' => 'success'],
                    ['label' => 'Rad etilgan', 'value' => $summary['rejected'], 'icon' => 'fa-times-circle', 'color' => 'danger'],
                ] as $card)
                    <div class="col-12 col-sm-6 col-lg-4 col-xl-2">
                        <div class="info-box shadow-sm">
                            <span class="info-box-icon bg-{{ $card['color'] }}">
                                <i class="fas {{ $card['icon'] }}" aria-hidden="true"></i>
                            </span>
                            <div class="info-box-content">
                                <span class="info-box-text">{{ $card['label'] }}</span>
                                <span class="info-box-number">{{ number_format($card['value'], 0, '.', ' ') }}</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold">AI inson tekshiruvi bo‘yicha mas’ullar</h3>
                    <div class="card-tools text-muted">
                        Yakunlangan: <strong>{{ number_format($summary['completion_rate'], 1) }}%</strong>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead>
                            <tr>
                                <th>Mas’ul</th>
                                <th class="text-center">HEMIS ID</th>
                                <th class="text-center">Jami</th>
                                <th class="text-center">Tekshirilgan</th>
                                <th class="text-center">Tekshirilmagan</th>
                                <th class="text-center">Qabul qilingan</th>
                                <th class="text-center">Rad etilgan</th>
                                <th class="text-center">Yakunlangan</th>
                                <th class="text-nowrap">Oxirgi biriktirish</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($statistics['reviewers'] as $reviewer)
                                <tr>
                                    <td class="font-weight-bold">{{ $reviewer['name'] }}</td>
                                    <td class="text-center text-nowrap">{{ $reviewer['hemis_id'] }}</td>
                                    <td class="text-center font-weight-bold">{{ number_format($reviewer['total']) }}</td>
                                    <td class="text-center text-info">{{ number_format($reviewer['checked']) }}</td>
                                    <td class="text-center text-warning font-weight-bold">{{ number_format($reviewer['unchecked']) }}</td>
                                    <td class="text-center text-success">{{ number_format($reviewer['approved']) }}</td>
                                    <td class="text-center text-danger">{{ number_format($reviewer['rejected']) }}</td>
                                    <td class="text-center">
                                        <span class="badge badge-{{ $reviewer['completion_rate'] >= 100 ? 'success' : 'info' }}">
                                            {{ number_format($reviewer['completion_rate'], 1) }}%
                                        </span>
                                    </td>
                                    <td class="text-nowrap">{{ $reviewer['last_assigned_at']->format('d.m.Y H:i') }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        AI inson tekshiruviga biriktirilgan resurslar topilmadi.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                            @if($statistics['reviewers'] !== [])
                                <tfoot>
                                <tr class="font-weight-bold bg-light">
                                    <td>Jami</td>
                                    <td></td>
                                    <td class="text-center">{{ number_format($summary['total']) }}</td>
                                    <td class="text-center">{{ number_format($summary['checked']) }}</td>
                                    <td class="text-center">{{ number_format($summary['unchecked']) }}</td>
                                    <td class="text-center">{{ number_format($summary['approved']) }}</td>
                                    <td class="text-center">{{ number_format($summary['rejected']) }}</td>
                                    <td class="text-center">{{ number_format($summary['completion_rate'], 1) }}%</td>
                                    <td></td>
                                </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
