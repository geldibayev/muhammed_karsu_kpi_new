@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <div class="row align-items-center">
                        <div class="col-md pr-md-3">
                            <h3 class="font-weight-bold mb-1">Biriktirilgan resurslar</h3>
                            <div class="small text-muted">
                                Sizga biriktirilgan mezonlardagi resurslar.
                            </div>
                        </div>
                        <div class="col-md-auto mt-2 mt-md-0">
                            <span class="badge badge-primary px-3 py-2">Jami: {{ $submissions->total() }}</span>
                        </div>
                    </div>
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
                    <form method="GET" action="{{ route('reviews.index') }}" class="mt-3">
                        <div class="form-row align-items-end">
                            <div class="form-group col-lg-3 col-md-4 mb-md-0">
                                <label for="status">Holati</label>
                                <select id="status" name="status" class="form-control">
                                    <option value="pending" @selected($selectedStatus === 'pending')>Tekshiruv kutilmoqda</option>
                                    <option value="accepted" @selected($selectedStatus === 'accepted')>Tasdiqlangan</option>
                                    <option value="cancelled" @selected($selectedStatus === 'cancelled')>Rad etilgan</option>
                                </select>
                            </div>
                            <div class="form-group col-lg-5 col-md-6 mb-md-0">
                                <label for="criterion">Kriteriya</label>
                                <select id="criterion" name="criterion" class="form-control @error('criterion') is-invalid @enderror">
                                    <option value="">Barcha kriteriyalar</option>
                                    @foreach($assignments as $assignment)
                                        <option value="{{ $assignment->criterion_id }}" @selected($selectedCriterionId === $assignment->criterion_id)>
                                            {{ $assignment->criterion_code }} — {{ data_get($assignment->criterion?->name, 'uz', 'Mezon topilmadi') }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('criterion')<div class="invalid-feedback">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-auto mt-2 mt-md-0">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-filter mr-1"></i> Filtrlash</button>
                                @if($selectedCriterionId !== null || $selectedStatus !== 'pending')
                                    <a href="{{ route('reviews.index') }}" class="btn btn-outline-secondary ml-1">Tozalash</a>
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
                                <th>Holati</th>
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
                                    <td class="align-middle">{{ data_get($datum->criterion?->name, 'uz', 'Mezon topilmadi') }}</td>
                                    <td class="align-middle">
                                        @php($datumStatus = \App\Enums\DatumStatus::from($datum->status))
                                        <span class="badge {{ $datumStatus->badgeClass() }}">{{ $datumStatus->label() }}</span>
                                    </td>
                                    <td class="align-middle">{{ $datum->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="align-middle text-right">
                                        <a href="{{ in_array($datum->status, ['received', 'checking'], true)
                                            ? route('reviews.show', $datum)
                                            : route('upload.details', $datum) }}" class="btn btn-outline-primary btn-sm">
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
                @if($submissions->hasPages())
                    <div class="card-footer">{{ $submissions->links() }}</div>
                @endif
            </div>
        </div>
    </section>
@endsection
