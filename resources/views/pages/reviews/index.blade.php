@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div>
                        <h3 class="card-title font-weight-bold mb-1">Baholash navbati</h3>
                        <div class="small text-muted">Sizga biriktirilgan, qaror kutayotgan resurslar.</div>
                    </div>
                    <span class="badge badge-primary px-3 py-2">Jami: {{ $pendingSubmissions->total() }}</span>
                </div>
                <div class="card-body border-bottom">
                    <div class="small text-muted mb-2">Biriktirilgan mezonlar</div>
                    @forelse($assignments as $assignment)
                        <span class="badge badge-light border mr-2 mb-1">
                            {{ $assignment->criterion_code }} — {{ data_get($assignment->criterion?->name, 'uz', 'Mezon topilmadi') }}
                        </span>
                    @empty
                        <span class="text-muted">Biriktirilgan mezon mavjud emas.</span>
                    @endforelse
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
                                <th>Holati</th>
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
                                    <td class="align-middle">{{ data_get($datum->criterion?->name, 'uz', 'Mezon topilmadi') }}</td>
                                    <td class="align-middle">
                                        @php($datumStatus = \App\Enums\DatumStatus::from($datum->status))
                                        <span class="badge {{ $datumStatus->badgeClass() }}">{{ $datumStatus->label() }}</span>
                                    </td>
                                    <td class="align-middle">{{ $datum->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="align-middle text-right">
                                        <a href="{{ route('reviews.show', $datum) }}" class="btn btn-outline-primary btn-sm">
                                            <i class="far fa-eye mr-1"></i> Ko‘rish
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">Baholash uchun yangi resurs yo‘q.</td>
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
