@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header px-4 py-3">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                        <div class="pr-md-4">
                            <h3 class="h5 font-weight-bold mb-1">{{ $status->label() }} resurslar</h3>
                            <p class="small text-muted mb-0">{{ $status->description() }}</p>
                        </div>
                        <span class="badge {{ $status->badgeClass() }} px-3 py-2 mt-3 mt-md-0 align-self-start align-self-md-center">
                            Jami: {{ $data->total() }}
                        </span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover small mb-0">
                            <thead>
                            <tr>
                                <th class="text-center" style="width: 6%;">#</th>
                                <th>Resurs ma’lumotlari</th>
                                <th class="text-center" style="width: 10%;">Yili</th>
                                <th class="text-center" style="width: 12%;">Holati</th>
                                <th class="text-center" style="width: 8%;">Ball</th>
                                <th class="text-center" style="width: 14%;">Yuborilgan vaqt</th>
                                <th class="text-center" style="width: 12%;">Amallar</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($data as $datum)
                                <tr>
                                    <td class="text-center align-middle font-weight-bold">#{{ $datum->id }}</td>
                                    <td class="align-middle">
                                        <div class="font-weight-bold text-break">{{ $datum->name }}</div>
                                        <div class="text-muted text-break">
                                            {{ data_get($datum->criterion?->name, 'uz', 'Mezon topilmadi') }}
                                        </div>
                                    </td>
                                    <td class="text-center align-middle">
                                        {{ $datum->year?->name ?? '—' }}
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge {{ $status->badgeClass() }}">{{ $status->label() }}</span>
                                    </td>
                                    <td class="text-center align-middle font-weight-bold">
                                        @if($status === \App\Enums\DatumStatus::Accepted)
                                            {{ number_format($datum->point, 2) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td class="text-center align-middle">
                                        {{ $datum->created_at->format('d.m.Y H:i') }}
                                    </td>
                                    <td class="text-center align-middle text-nowrap">
                                        <a href="{{ route('upload.details', $datum) }}"
                                           class="btn btn-outline-primary btn-xs" title="Batafsil ko‘rish">
                                            <i class="fas fa-eye mr-1"></i> Ko‘rish
                                        </a>

                                        @if($datum->storagePath() !== null)
                                            <a href="{{ route('upload.file.download', $datum) }}"
                                               class="btn btn-outline-secondary btn-xs" title="Faylni yuklab olish">
                                                <i class="fas fa-download"></i>
                                            </a>
                                        @elseif($datum->externalUrl() !== null)
                                            <a href="{{ $datum->externalUrl() }}" target="_blank"
                                               rel="noopener noreferrer" class="btn btn-outline-secondary btn-xs"
                                               title="Havolani ochish">
                                                <i class="fas fa-external-link-alt"></i>
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="far fa-folder-open fa-2x d-block mb-2"></i>
                                        Bu holatda resurslar mavjud emas.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($data->hasPages())
                    <div class="card-footer clearfix">
                        {{ $data->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
