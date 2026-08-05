@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h1 class="h5 font-weight-bold mb-1">
                            {{ data_get($criterion->name, 'uz', 'Nomsiz kriteriya') }}
                        </h1>
                        <div class="small text-muted">
                            {{ data_get($criterion->report?->name, 'uz', 'Hisobot davri ko‘rsatilmagan') }}
                        </div>
                    </div>
                    <a href="{{ route('home') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left mr-1"></i> Asosiy sahifaga qaytish
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                            <tr>
                                <th class="text-center" style="width: 8%;">O‘rin</th>
                                <th>Professor-o‘qituvchi</th>
                                <th>Bo‘lim va lavozim</th>
                                <th class="text-center" style="width: 15%;">Tasdiqlangan resurslar</th>
                                <th class="text-center" style="width: 15%;">Ball</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($rankedPoints as $point)
                                @php
                                    $workplace = $point->user?->ratingWorkplace;
                                    $department = $workplace?->department;
                                @endphp
                                <tr>
                                    <td class="text-center align-middle">
                                        <span class="badge badge-primary px-3 py-2">
                                            {{ $rankedPoints->firstItem() + $loop->index }}
                                        </span>
                                    </td>
                                    <td class="align-middle">
                                        <div class="font-weight-bold">
                                            @if($point->user)
                                                <a href="{{ route('ratings.show', $point->user) }}"
                                                   class="text-primary"
                                                   title="Foydalanuvchi resurslarini ko‘rish">
                                                    {{ $point->user->full ?: ($point->user->short ?: 'Noma’lum foydalanuvchi') }}
                                                    <i class="fas fa-folder-open ml-1" aria-hidden="true"></i>
                                                    <span class="sr-only">— foydalanuvchi resurslarini ko‘rish</span>
                                                </a>
                                            @else
                                                Noma’lum foydalanuvchi
                                            @endif
                                        </div>
                                        <div class="small text-muted">
                                            HEMIS ID: {{ $point->user?->hemis_id ?? 'ko‘rsatilmagan' }}
                                        </div>
                                    </td>
                                    <td class="align-middle">
                                        <div>{{ data_get($department?->name, 'uz', 'Bo‘lim biriktirilmagan') }}</div>
                                        <div class="small text-muted">
                                            {{ $workplace?->position?->name ?? 'Lavozim biriktirilmagan' }}
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge badge-success px-3 py-2">
                                            {{ $point->user?->accepted_submissions_count ?? 0 }} ta
                                        </span>
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="font-weight-bold text-success">
                                            {{ number_format($point->point, 2) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-5">
                                        Ushbu kriteriya bo‘yicha reyting natijalari hali mavjud emas.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($rankedPoints->hasPages())
                    <div class="card-footer">
                        {{ $rankedPoints->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
