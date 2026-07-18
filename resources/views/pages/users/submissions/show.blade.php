@extends('layouts.app')

@section('content')
    @php
        $metadataLabels = [
            'name' => 'Resurs nomi',
            'keywords' => 'Kalit so‘zlar',
            'lang' => 'Til identifikatori',
            'authors_num' => 'Mualliflar soni',
            'division' => 'Mualliflar soni',
            'authors' => 'Mualliflar',
            'doi' => 'DOI',
            'journal' => 'Jurnal',
            'publisher' => 'Nashriyot',
            'params' => 'Nashr parametrlari',
            'publish_params' => 'Nashr parametrlari',
            'certificate_no' => 'Guvohnoma raqami',
            'certificate_date' => 'Guvohnoma sanasi',
            'form' => 'Mulk turi',
        ];
    @endphp

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h3 class="card-title font-weight-bold">Resurs #{{ $datum->id }}</h3>
                            <span class="badge {{ $status->badgeClass() }} px-3 py-2">
                                {{ $status->label() }}
                            </span>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Resurs nomi</dt>
                                <dd class="col-sm-8 text-break">{{ $datum->name }}</dd>

                                <dt class="col-sm-4">Mezon</dt>
                                <dd class="col-sm-8 text-break">
                                    {{ data_get($datum->criterion?->name, 'uz', 'Mezon topilmadi') }}
                                </dd>

                                <dt class="col-sm-4">Resurs yili</dt>
                                <dd class="col-sm-8">{{ $datum->year?->name ?? 'Ko‘rsatilmagan' }}</dd>

                                <dt class="col-sm-4">Yuborilgan vaqt</dt>
                                <dd class="col-sm-8">{{ $datum->created_at->format('d.m.Y H:i:s') }}</dd>

                                <dt class="col-sm-4">Ball</dt>
                                <dd class="col-sm-8 font-weight-bold">
                                    {{ $status === \App\Enums\DatumStatus::Accepted ? number_format($datum->point, 2) : '—' }}
                                </dd>

                                <dt class="col-sm-4">Tekshiruv xulosasi</dt>
                                <dd class="col-sm-8 text-break" style="white-space: pre-line;">{{ $datum->reason ?: 'Xulosa hali mavjud emas.' }}</dd>
                            </dl>
                        </div>
                        <div class="card-footer">
                            <a href="{{ route('files.show', $status) }}" class="btn btn-default btn-sm">
                                <i class="fas fa-arrow-left mr-1"></i> Ro‘yxatga qaytish
                            </a>

                            @if($datum->storagePath() !== null)
                                <a href="{{ route('upload.file.download', $datum) }}"
                                   class="btn btn-primary btn-sm float-right">
                                    <i class="fas fa-download mr-1"></i> Faylni yuklab olish
                                </a>
                            @elseif($datum->externalUrl() !== null)
                                <a href="{{ $datum->externalUrl() }}" target="_blank" rel="noopener noreferrer"
                                   class="btn btn-primary btn-sm float-right">
                                    <i class="fas fa-external-link-alt mr-1"></i> Havolani ochish
                                </a>
                            @endif
                        </div>
                    </div>

                    @if($datum->submissionMetadata() !== [])
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title font-weight-bold">Kiritilgan qo‘shimcha ma’lumotlar</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <tbody>
                                        @foreach($datum->submissionMetadata() as $key => $value)
                                            <tr>
                                                <th style="width: 35%;">{{ $metadataLabels[$key] ?? $key }}</th>
                                                <td class="text-break">{{ $value }}</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">O‘zgarishlar tarixi</h3>
                        </div>
                        <div class="card-body">
                            @forelse($datum->histories as $history)
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between">
                                        <span class="badge badge-{{ $history->type === 'error' ? 'danger' : $history->type }}">
                                            {{ str_starts_with($history->message_type, 'ai_') ? 'AI tekshiruvi' : 'Tizim hodisasi' }}
                                        </span>
                                        <small class="text-muted">{{ $history->created_at->format('d.m.Y H:i') }}</small>
                                    </div>
                                    <div class="small mt-2 text-break">{{ $history->message }}</div>
                                </div>
                            @empty
                                <div class="text-muted text-center py-3">Tarix yozuvlari mavjud emas.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
