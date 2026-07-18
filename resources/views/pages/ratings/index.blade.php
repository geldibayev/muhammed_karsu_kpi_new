@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary">
                <div class="card-header px-4 py-3">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                        <div class="pr-md-4">
                            <h3 class="h5 font-weight-bold mb-1">Foydalanuvchilar reytingi</h3>
                            <p class="small text-muted mb-0">
                                @if($report)
                                    {{ data_get($report->name, 'uz', 'Faol hisobot') }} bo‘yicha jami ballar
                                @else
                                    Faol hisobot topilmadi
                                @endif
                            </p>
                        </div>
                        <span class="badge badge-primary px-3 py-2 mt-3 mt-md-0 align-self-start align-self-md-center">
                            Jami: {{ $users->total() }}
                        </span>
                    </div>
                </div>

                <div class="card-header p-0 border-bottom-0">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a href="{{ route('ratings.index', array_merge($filters, ['degree_group' => 'with_degree'])) }}"
                               class="nav-link @if($filters['degree_group'] === 'with_degree') active @endif">
                                <i class="fas fa-user-graduate mr-1"></i>
                                Ilmiy darajaga ega
                            </a>
                        </li>
                        <li class="nav-item">
                            <a href="{{ route('ratings.index', array_merge($filters, ['degree_group' => 'without_degree'])) }}"
                               class="nav-link @if($filters['degree_group'] === 'without_degree') active @endif">
                                <i class="fas fa-users mr-1"></i>
                                Ilmiy darajaga ega emas
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="card-body border-bottom">
                    <form method="GET" action="{{ route('ratings.index') }}">
                        <input type="hidden" name="degree_group" value="{{ $filters['degree_group'] }}">
                        <div class="row">
                            <div class="col-lg-4 col-md-6 mb-3 mb-lg-0">
                                <label class="small font-weight-bold" for="rating-search">Foydalanuvchini izlash</label>
                                <div class="input-group">
                                    <input id="rating-search" type="search" name="search" class="form-control"
                                           value="{{ $filters['search'] ?? '' }}" placeholder="F.I.Sh. bo‘yicha izlash">
                                    <div class="input-group-append">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                                <label class="small font-weight-bold" for="rating-faculty">Fakultet</label>
                                <select id="rating-faculty" name="faculty" class="form-control">
                                    <option value="">Barcha fakultetlar</option>
                                    @foreach($faculties as $faculty)
                                        <option value="{{ $faculty->id }}"
                                            @selected((int) ($filters['faculty'] ?? 0) === $faculty->id)>
                                            {{ data_get($faculty->name, 'uz', 'Nomsiz fakultet') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                                <label class="small font-weight-bold" for="rating-department">Kafedra</label>
                                <select id="rating-department" name="department" class="form-control">
                                    <option value="">Barcha kafedralar</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}"
                                            @selected((int) ($filters['department'] ?? 0) === $department->id)>
                                            {{ data_get($department->name, 'uz', 'Nomsiz kafedra') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-lg-2 col-md-6 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary mr-2">
                                    <i class="fas fa-filter mr-1"></i> Filtrlash
                                </button>
                                <a href="{{ route('ratings.index', ['degree_group' => $filters['degree_group']]) }}"
                                   class="btn btn-outline-secondary" title="Filtrlarni tozalash">
                                    <i class="fas fa-times"></i>
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover small mb-0">
                            <thead>
                            <tr>
                                <th class="text-center">O‘rin</th>
                                <th>Foydalanuvchi</th>
                                <th>Fakultet</th>
                                <th>Kafedra</th>
                                <th>Asosiy lavozimi</th>
                                <th class="text-center">Jami ball</th>
                                <th class="text-center">Amal</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($users as $user)
                                @php
                                    $workplace = $user->primaryWorkplace;
                                    $department = $workplace?->department;
                                    $faculty = $department?->parent ?? ($department?->parent_id === null ? $department : null);
                                @endphp
                                <tr>
                                    <td class="text-center align-middle font-weight-bold">
                                        {{ $users->firstItem() + $loop->index }}
                                    </td>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            <img src="{{ $user->image_url ?: asset('dist/img/default-150x150.png') }}"
                                                 alt="{{ $user->full ?: 'Foydalanuvchi' }}"
                                                 class="img-circle elevation-1 img-size-50 mr-3" loading="lazy">
                                            <span class="font-weight-bold">
                                                {{ $user->full ?: ($user->short ?: 'Noma’lum foydalanuvchi') }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        {{ data_get($faculty?->name, 'uz', '—') }}
                                    </td>
                                    <td class="align-middle">
                                        @if($department?->parent_id !== null)
                                            {{ data_get($department->name, 'uz', '—') }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="align-middle">
                                        {{ $workplace?->position?->name ?? '—' }}
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge badge-success px-3 py-2">
                                            {{ number_format((float) ($user->total_points ?? 0), 2) }}
                                        </span>
                                    </td>
                                    <td class="text-center align-middle">
                                        <a href="{{ route('ratings.show', array_merge(['user' => $user], $filters)) }}"
                                           class="btn btn-outline-primary btn-xs">
                                            <i class="fas fa-eye mr-1"></i> Ko‘rish
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fas fa-users fa-2x d-block mb-2"></i>
                                        Tanlangan shartlar bo‘yicha foydalanuvchilar topilmadi.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($users->hasPages())
                    <div class="card-footer clearfix">
                        {{ $users->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
