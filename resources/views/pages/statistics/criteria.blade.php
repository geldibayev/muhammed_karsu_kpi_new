@extends('layouts.app')

@section('content')
    @php
        $columns = [
            'total' => 'Jami',
            'checked' => 'Tekshirilgan',
            'unchecked' => 'Tekshirilmagan',
            'returned' => 'Qaytarilgan',
            'deleted' => 'O‘chirilgan',
            'other' => 'Boshqa',
        ];
    @endphp

    <section class="content">
        <div class="card">
            <div class="card-header">
                <h3 class="card-title font-weight-bold">Kriteriyalar bo‘yicha yuklangan resurslar</h3>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover table-bordered mb-0">
                    <thead>
                    <tr>
                        <th class="text-center" style="width: 10%;">Kod</th>
                        <th>Kriteriya</th>
                        @foreach($columns as $column => $label)
                            @php($nextDirection = $sort === $column && $direction === 'desc' ? 'asc' : 'desc')
                            <th class="text-center text-nowrap">
                                <a href="{{ route('criterion-resource-statistics.index', ['sort' => $column, 'direction' => $nextDirection]) }}"
                                   class="text-dark">
                                    {{ $label }}
                                    @if($sort === $column)
                                        <i class="fas fa-sort-{{ $direction === 'asc' ? 'up' : 'down' }} ml-1"
                                           aria-hidden="true"></i>
                                    @else
                                        <i class="fas fa-sort text-muted ml-1" aria-hidden="true"></i>
                                    @endif
                                </a>
                            </th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($criteria as $criterion)
                        <tr>
                            <td class="align-middle text-center font-weight-bold">
                                {{ $criterion->code ?: '—' }}
                            </td>
                            <td class="align-middle">
                                <div class="small text-muted">
                                    {{ data_get($criterion->parent?->name, 'uz', 'Nomsiz bo‘lim') }}
                                </div>
                                {{ data_get($criterion->name, 'uz', 'Nomsiz kriteriya') }}
                            </td>
                            @foreach(array_keys($columns) as $column)
                                <td class="align-middle text-center font-weight-bold">
                                    {{ number_format((int) $criterion->{$column}, 0, '.', ' ') }}
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">
                                Faol hisobot uchun kriteriyalar topilmadi.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
