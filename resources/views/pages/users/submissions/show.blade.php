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
                            @if($status === \App\Enums\DatumStatus::Deleted)
                                <div class="alert alert-warning">
                                    <h4 class="h6 font-weight-bold">
                                        <i class="fas fa-info-circle mr-1" aria-hidden="true"></i>
                                        Resurs hisobdan chiqarilgan
                                    </h4>
                                    <div class="text-break">{{ $datum->reason ?: 'O‘chirish sababi ko‘rsatilmagan.' }}</div>
                                    @if($datum->duplicateOf !== null)
                                        <div class="mt-2">
                                            <span class="font-weight-bold">Qoldirilgan resurs:</span>
                                            <a href="{{ route('upload.details', $datum->duplicateOf) }}">
                                                #{{ $datum->duplicateOf->id }} — {{ $datum->duplicateOf->name }}
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <dl class="row mb-0">
                                <dt class="col-sm-4">Resurs nomi</dt>
                                <dd class="col-sm-8 text-break">{{ $datum->name }}</dd>

                                <dt class="col-sm-4">Yuklagan foydalanuvchi</dt>
                                <dd class="col-sm-8 text-break">
                                    {{ $datum->user?->full ?: ($datum->user?->short ?: 'Noma’lum foydalanuvchi') }}
                                </dd>

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
                            <a href="{{ $datum->user_id === auth()->id() ? route('files.show', $status) : route('ratings.show', $datum->user_id) }}"
                               class="btn btn-default btn-sm">
                                <i class="fas fa-arrow-left mr-1"></i> Ro‘yxatga qaytish
                            </a>

                            @can('requeueAiEvaluation', $datum)
                                <form action="{{ route('upload.ai-requeue', $datum) }}" method="post"
                                      class="d-inline-block ml-2">
                                    @csrf
                                    <button type="submit" class="btn btn-warning btn-sm">
                                        <i class="fas fa-robot mr-1" aria-hidden="true"></i>
                                        AI tekshiruviga qayta yuborish
                                    </button>
                                </form>
                            @endcan

                            @can('overrideAcceptance', $datum)
                                <button type="button" class="btn btn-danger btn-sm ml-2"
                                        data-toggle="modal" data-target="#reject-accepted-ai-modal">
                                    <i class="fas fa-user-times mr-1"></i>
                                    Tasdiqlangan resursni rad etish
                                </button>
                            @endcan

                            @can('overrideCancellation', $datum)
                                @if($decisionOverridePointMaximum !== null)
                                    <button type="button" class="btn btn-success btn-sm ml-2"
                                            data-toggle="modal" data-target="#approve-cancelled-ai-modal">
                                        <i class="fas fa-user-check mr-1"></i>
                                        Rad etilgan resursni tasdiqlash
                                    </button>
                                @else
                                    <button type="button" class="btn btn-success btn-sm ml-2" disabled
                                            title="Foydalanuvchi uchun maksimal ball sozlanmagan">
                                        <i class="fas fa-user-check mr-1"></i>
                                        Rad etilgan resursni tasdiqlash
                                    </button>
                                @endif
                            @endcan

                            @can('download', $datum)
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
                            @endcan
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
                                    <div class="small mt-2 text-break">{{ $history->messageForSubmitter() }}</div>
                                </div>
                            @empty
                                <div class="text-muted text-center py-3">Tarix yozuvlari mavjud emas.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            @can('overrideAcceptance', $datum)
                <div class="modal fade" id="reject-accepted-ai-modal" tabindex="-1" role="dialog"
                     aria-labelledby="reject-accepted-ai-modal-title" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <form method="POST" action="{{ route('ai-human-reviews.reject-accepted', $datum) }}"
                              class="modal-content">
                            @csrf
                            @method('PATCH')
                            <div class="modal-header">
                                <h5 class="modal-title" id="reject-accepted-ai-modal-title">
                                    Tasdiqlangan resursni rad etish
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Yopish">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <label for="accepted-ai-rejection-reason">
                                    Oldingi qarordagi xato va rad etish sababi
                                </label>
                                <textarea id="accepted-ai-rejection-reason" name="reason" rows="5"
                                          maxlength="5000" required
                                          class="form-control @error('reason') is-invalid @enderror">{{ old('reason') }}</textarea>
                                @error('reason')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Bekor qilish</button>
                                <button type="submit" class="btn btn-danger">Izoh bilan rad etish</button>
                            </div>
                        </form>
                    </div>
                </div>
            @endcan

            @can('overrideCancellation', $datum)
                @if($decisionOverridePointMaximum !== null)
                <div class="modal fade" id="approve-cancelled-ai-modal" tabindex="-1" role="dialog"
                     aria-labelledby="approve-cancelled-ai-modal-title" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                        <form method="POST" action="{{ route('ai-human-reviews.approve-cancelled', $datum) }}"
                              class="modal-content">
                            @csrf
                            @method('PATCH')
                            <div class="modal-header">
                                <h5 class="modal-title" id="approve-cancelled-ai-modal-title">
                                    Rad etilgan resursni tasdiqlash
                                </h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Yopish">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body">
                                <div class="alert alert-info py-2">
                                    Ruxsat etilgan ball oralig‘i:
                                    <strong>0–{{ number_format((float) $decisionOverridePointMaximum, 4, '.', '') }}</strong>
                                </div>
                                <label for="cancelled-ai-approval-point">Tasdiqlash balli</label>
                                <input id="cancelled-ai-approval-point" type="number" name="point"
                                       min="0" max="{{ $decisionOverridePointMaximum }}" step="0.0001" required
                                       value="{{ old('point') }}"
                                       class="form-control @error('point') is-invalid @enderror">
                                @error('point')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Bekor qilish</button>
                                <button type="submit" class="btn btn-success">Ball bilan tasdiqlash</button>
                            </div>
                        </form>
                    </div>
                </div>
                @endif
            @endcan
        </div>
    </section>
@endsection

@section('script')
    @if($errors->has('point'))
        <script>$('#approve-cancelled-ai-modal').modal('show');</script>
    @elseif($errors->has('reason'))
        <script>$('#reject-accepted-ai-modal').modal('show');</script>
    @endif
@endsection
