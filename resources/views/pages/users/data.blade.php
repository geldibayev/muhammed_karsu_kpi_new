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
                        <div class="d-flex flex-wrap align-items-center mt-3 mt-md-0">
                            <span class="badge {{ $status->badgeClass() }} px-3 py-2 mr-2">
                                Jami: {{ $data->total() }}
                            </span>
                            @if($status === \App\Enums\DatumStatus::Accepted)
                                <span class="badge badge-primary px-3 py-2">
                                    Yakuniy jami ball: {{ number_format($totalPoints, 2) }}
                                </span>
                            @endif
                        </div>
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
                                        @if(auth()->user()->isSuperAdmin() && $status === \App\Enums\DatumStatus::Cancelled)
                                            <div class="small text-info text-break">
                                                <i class="fas fa-user mr-1" aria-hidden="true"></i>
                                                {{ $datum->user?->full ?: ($datum->user?->short ?: 'Noma’lum foydalanuvchi') }}
                                                · HEMIS ID: {{ $datum->user?->hemis_id ?? '—' }}
                                            </div>
                                        @endif
                                        @if($status === \App\Enums\DatumStatus::Deleted)
                                            <div class="alert alert-warning px-3 py-2 mt-2 mb-0">
                                                <div class="text-break">
                                                    <i class="fas fa-info-circle mr-1" aria-hidden="true"></i>
                                                    {{ $datum->reason ?: 'O‘chirish sababi ko‘rsatilmagan.' }}
                                                </div>
                                                @if($datum->duplicateOf !== null)
                                                    <div class="mt-1">
                                                        <span class="font-weight-bold">Qoldirilgan resurs:</span>
                                                        <a href="{{ route('upload.details', $datum->duplicateOf) }}">
                                                            #{{ $datum->duplicateOf->id }} — {{ $datum->duplicateOf->name }}
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
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

                                        @can('download', $datum)
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
                                        @endcan

                                        @can('requeueAiEvaluation', $datum)
                                            <form action="{{ route('upload.ai-requeue', $datum) }}" method="post"
                                                  class="d-inline-block">
                                                @csrf
                                                <button type="submit" class="btn btn-warning btn-xs"
                                                        title="AI tekshiruviga qayta yuborish">
                                                    <i class="fas fa-robot mr-1" aria-hidden="true"></i>
                                                    AI tekshiruviga qayta yuborish
                                                </button>
                                            </form>
                                        @endcan
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
