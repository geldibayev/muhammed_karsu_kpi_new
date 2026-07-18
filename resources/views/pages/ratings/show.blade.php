@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="container-fluid">
            @php
                $workplace = $user->primaryWorkplace;
                $department = $workplace?->department;
                $faculty = $department?->parent ?? ($department?->parent_id === null ? $department : null);
            @endphp

            <div class="card card-outline card-primary">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <img src="{{ $user->image_url ?: asset('dist/img/default-150x150.png') }}"
                                 alt="{{ $user->full ?: 'Foydalanuvchi' }}"
                                 class="img-circle elevation-1 img-size-64 mr-3">
                            <div>
                                <h1 class="h5 font-weight-bold mb-1">
                                    {{ $user->full ?: ($user->short ?: 'Noma’lum foydalanuvchi') }}
                                </h1>
                                <div class="small text-muted">
                                    {{ data_get($faculty?->name, 'uz', 'Fakultet biriktirilmagan') }}
                                    @if($department?->parent_id !== null)
                                        / {{ data_get($department->name, 'uz', 'Kafedra biriktirilmagan') }}
                                    @endif
                                </div>
                                <div class="small text-muted">{{ $workplace?->position?->name ?? 'Lavozim biriktirilmagan' }}</div>
                            </div>
                        </div>
                        <a href="{{ route('ratings.index', $filters) }}" class="btn btn-outline-secondary mt-3 mt-md-0">
                            <i class="fas fa-arrow-left mr-1"></i> Reytingga qaytish
                        </a>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h2 class="h6 font-weight-bold mb-1">Kriteriyalar bo‘yicha ballar</h2>
                        <div class="small text-muted">
                            {{ $report ? data_get($report->name, 'uz', 'Faol hisobot') : 'Faol hisobot topilmadi' }}
                        </div>
                    </div>
                    <span class="badge badge-success px-3 py-2">Jami: {{ number_format($totalPoints, 2) }}</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover small mb-0">
                            <thead>
                            <tr>
                                <th class="text-center">#</th>
                                <th>Kriteriya</th>
                                <th class="text-center">Ball</th>
                                <th>Baholagan</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($criterionSections as $section)
                                <tr class="bg-light">
                                    <th class="text-center align-middle py-3">#{{ $section['number'] }}</th>
                                    <th colspan="3" class="align-middle py-3">
                                        {{ data_get($section['criterion']->name, 'uz', 'Nomsiz bo‘lim') }}
                                    </th>
                                </tr>
                                @foreach($section['rows'] as $score)
                                    <tr>
                                        <td class="text-center align-middle font-weight-bold">{{ $score['code'] }}</td>
                                        <td class="align-middle font-weight-bold">
                                            {{ data_get($score['criterion']->name, 'uz', 'Nomsiz kriteriya') }}
                                        </td>
                                        <td class="text-center align-middle">
                                            @if($score['state'] === 'scored')
                                                <span class="badge badge-success px-3 py-2">
                                                    {{ number_format($score['point'], 2) }}
                                                </span>
                                            @elseif($score['state'] === 'pending')
                                                <span class="badge badge-warning px-3 py-2">Baholanmagan</span>
                                            @elseif($score['state'] === 'accepted')
                                                <span class="badge badge-info px-3 py-2">Tasdiqlangan</span>
                                            @elseif($score['state'] === 'cancelled')
                                                <span class="badge badge-danger px-3 py-2">Qaytarilgan</span>
                                            @else
                                                <span class="badge badge-secondary px-3 py-2">Yuklanmagan</span>
                                            @endif
                                            @if($score['pending_count'] > 0)
                                                <span class="badge badge-warning d-block mt-1">
                                                    {{ $score['pending_count'] }} ta baholanmagan yuklama
                                                </span>
                                            @endif
                                        </td>
                                        <td class="align-middle">
                                            @foreach($score['evaluators'] as $evaluator)
                                                <span class="badge {{ $evaluator['type'] === 'manual' ? 'badge-primary' : ($evaluator['type'] === 'ai' ? 'badge-info' : ($evaluator['type'] === 'pending' ? 'badge-warning' : 'badge-secondary')) }} mr-1">
                                                    @if($evaluator['type'] === 'ai')
                                                        <i class="fas fa-robot mr-1"></i>
                                                    @elseif($evaluator['type'] === 'pending')
                                                        <i class="fas fa-clock mr-1"></i>
                                                    @elseif($evaluator['type'] === 'unuploaded')
                                                        <i class="fas fa-upload mr-1"></i>
                                                    @else
                                                        <i class="fas fa-user-check mr-1"></i>
                                                    @endif
                                                    {{ $evaluator['name'] }}
                                                </span>
                                            @endforeach
                                        </td>
                                    </tr>
                                @endforeach
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-5">
                                        Faol hisobot uchun kriteriyalar mavjud emas.
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
