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
                                {{ $isSuperAdmin ? 'Barcha baholanmagan AI resurslari.' : 'AI yakuniy qarorni insonga qoldirgan va sizga biriktirilgan resurslar.' }}
                            </div>
                        </div>
                        <div class="col-md-auto mt-2 mt-md-0">
                            <span class="badge badge-info px-3 py-2">Jami: {{ $pendingSubmissions->total() }}</span>
                        </div>
                    </div>
                </div>
                <div class="card-body border-bottom">
                    <form method="GET" action="{{ route('ai-human-reviews.index') }}">
                        <div class="form-row align-items-end">
                            <div class="form-group col-lg-6 col-md-8 mb-md-0">
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
                                @if($selectedCriterionId !== null)
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
                                <th>AI xulosasi</th>
                                <th>Yuborilgan vaqt</th>
                                <th class="text-right">Amal</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($pendingSubmissions as $datum)
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
                                    <td class="align-middle text-break">{{ $datum->reason ?: 'Izoh mavjud emas' }}</td>
                                    <td class="align-middle">{{ $datum->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="align-middle text-right">
                                        <a href="{{ route('reviews.show', [
                                            'datum' => $datum,
                                            'criterion' => $selectedCriterionId,
                                            'page' => $pendingSubmissions->currentPage(),
                                        ]) }}"
                                           class="btn btn-outline-info btn-sm">
                                            <i class="far fa-eye mr-1"></i> Ko‘rish
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        Inson tekshiruvi uchun AI resurslari yo‘q.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if($pendingSubmissions->hasPages())
                    <div class="card-footer">{{ $pendingSubmissions->links() }}</div>
                @endif
            </div>
        </div>
    </section>
@endsection
