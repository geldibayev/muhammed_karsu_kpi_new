@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-info">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md pr-md-3">
                            <h3 class="font-weight-bold mb-1">AI inson tekshiruvi</h3>
                            <div class="small text-muted">
                                Sizga biriktirilgan AI resurslari va ularning yakuniy holati.
                            </div>
                        </div>
                        <div class="col-md-auto mt-2 mt-md-0">
                            <span class="badge badge-info px-3 py-2">Jami: {{ $submissions->total() }}</span>
                        </div>
                    </div>
                </div>
                <div class="card-body border-bottom">
                    <form method="GET" action="{{ route('ai-human-reviews.index') }}">
                        <div class="form-row align-items-end">
                            <div class="form-group col-lg-3 col-md-4 mb-md-0">
                                <label for="status">Holati</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="pending" @selected($selectedStatus === 'pending')>Tekshiruv kutilmoqda</option>
                                    <option value="accepted" @selected($selectedStatus === 'accepted')>Tasdiqlangan</option>
                                    <option value="cancelled" @selected($selectedStatus === 'cancelled')>Rad etilgan</option>
                                    <option value="scopus_audit" @selected($selectedStatus === 'scopus_audit')>Scopus PDF auditida rad etilgan</option>
                                </select>
                            </div>
                            <div class="form-group col-lg-5 col-md-6 mb-md-0">
                                <label for="criterion">Kriteriya bo‘yicha filtr</label>
                                <select id="criterion" name="criterion"
                                        class="form-control @error('criterion') is-invalid @enderror">
                                    <option value="">Barcha kriteriyalar</option>
                                    @foreach($criteria as $criterion)
                                        <option value="{{ $criterion->id }}"
                                                @selected($selectedCriterionId === $criterion->id)>
                                            @if(filled($criterion->code)){{ $criterion->code }} — @endif{{ data_get($criterion->name, 'uz', 'Nomsiz kriteriya') }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('criterion')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-auto mt-2 mt-md-0">
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-filter mr-1"></i> Filtrlash
                                </button>
                                @if($selectedCriterionId !== null || $selectedStatus !== 'pending')
                                    <a href="{{ route('ai-human-reviews.index') }}"
                                       class="btn btn-outline-secondary ml-1">
                                        Tozalash
                                    </a>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                            <tr>
                                <th>#</th>
                                <th>Resurs</th>
                                <th>Muallif</th>
                                <th>Mezon</th>
                                <th>Mas’ul</th>
                                <th>AI xulosasi</th>
                                <th>Yuborilgan vaqt</th>
                                <th class="text-right">Amal</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($submissions as $datum)
                                <tr>
                                    <td class="align-middle">{{ $datum->id }}</td>
                                    <td class="align-middle font-weight-bold text-break">{{ $datum->name }}</td>
                                    <td class="align-middle">
                                        <div>{{ $datum->user?->full ?: $datum->user?->short ?: 'Noma’lum' }}</div>
                                        <small class="text-muted">HEMIS ID: {{ $datum->user?->hemis_id ?? '—' }}</small>
                                    </td>
                                    <td class="align-middle">
                                        {{ data_get($datum->criterion?->name, 'uz', 'Mezon topilmadi') }}
                                    </td>
                                    <td class="align-middle">
                                        @if($datum->reviewer)
                                            <div>{{ $datum->reviewer->full ?: $datum->reviewer->short }}</div>
                                            <small class="text-muted">HEMIS ID: {{ $datum->reviewer_hemis_id }}</small>
                                        @elseif($datum->reviewer_hemis_id)
                                            HEMIS ID: {{ $datum->reviewer_hemis_id }}
                                        @else
                                            <span class="text-muted">Biriktirilmagan</span>
                                        @endif
                                    </td>
                                    <td class="align-middle text-break">{{ $datum->reason ?: 'Izoh mavjud emas' }}</td>
                                    <td class="align-middle">{{ $datum->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="align-middle text-right">
                                        <a href="{{ in_array($datum->status, ['received', 'checking'], true)
                                            ? route('reviews.show', [
                                                'datum' => $datum,
                                                'criterion' => $selectedCriterionId,
                                                'page' => $submissions->currentPage(),
                                            ])
                                            : route('upload.details', $datum) }}"
                                           class="btn btn-outline-info btn-sm">
                                            <i class="far fa-eye mr-1"></i> Ko‘rish
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        Inson tekshiruvi uchun AI resurslari yo‘q.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($submissions->hasPages())
                    <div class="card-footer">{{ $submissions->links() }}</div>
                @endif
            </div>
        </div>
    </section>
@endsection
