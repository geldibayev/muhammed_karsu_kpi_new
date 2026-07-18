@extends('layouts.app')

@section('content')
    @php
        $metadataLabels = [
            'name' => 'Resurs nomi',
            'keywords' => 'Kalit so‘zlar',
            'authors_num' => 'Mualliflar soni',
            'authors' => 'Mualliflar',
            'doi' => 'DOI',
            'journal' => 'Jurnal',
            'publisher' => 'Nashriyot',
            'certificate_no' => 'Guvohnoma raqami',
            'certificate_date' => 'Guvohnoma sanasi',
        ];
    @endphp

    <section class="content">
        <div class="container-fluid">
            @if($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0 pl-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="row">
                <div class="col-lg-8">
                    <div class="card card-outline card-primary">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h3 class="card-title font-weight-bold">Resurs #{{ $datum->id }}</h3>
                            <span class="badge {{ $status->badgeClass() }} px-3 py-2">{{ $status->label() }}</span>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Resurs nomi</dt>
                                <dd class="col-sm-8 text-break">{{ $datum->name }}</dd>
                                <dt class="col-sm-4">Muallif</dt>
                                <dd class="col-sm-8">
                                    {{ $datum->user?->full ?: $datum->user?->short ?: 'Noma’lum' }}
                                    <span class="text-muted">({{ $datum->user?->hemis_id ?? 'HEMIS ID yo‘q' }})</span>
                                </dd>
                                <dt class="col-sm-4">Mezon</dt>
                                <dd class="col-sm-8">{{ data_get($datum->criterion?->name, 'uz', 'Mezon topilmadi') }}</dd>
                                <dt class="col-sm-4">Resurs yili</dt>
                                <dd class="col-sm-8">{{ $datum->year?->name ?? 'Ko‘rsatilmagan' }}</dd>
                                <dt class="col-sm-4">Yuborilgan vaqt</dt>
                                <dd class="col-sm-8">{{ $datum->created_at->format('d.m.Y H:i:s') }}</dd>
                            </dl>
                        </div>
                        <div class="card-footer d-flex flex-wrap align-items-center">
                            <a href="{{ route('reviews.index') }}" class="btn btn-default btn-sm mr-2">
                                <i class="fas fa-arrow-left mr-1"></i> Ro‘yxatga qaytish
                            </a>
                            @if($datum->storagePath() !== null)
                                <a href="{{ route('upload.file.download', $datum) }}" class="btn btn-outline-primary btn-sm mr-auto">
                                    <i class="fas fa-download mr-1"></i> Faylni yuklab olish
                                </a>
                            @elseif($datum->externalUrl() !== null)
                                <a href="{{ $datum->externalUrl() }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-primary btn-sm mr-auto">
                                    <i class="fas fa-external-link-alt mr-1"></i> Havolani ochish
                                </a>
                            @else
                                <span class="mr-auto"></span>
                            @endif

                            <form method="POST" action="{{ route('reviews.approve', $datum) }}" class="mr-2">
                                @csrf
                                @method('PATCH')
                                <button type="submit" class="btn btn-success btn-sm">
                                    <i class="fas fa-check mr-1"></i> Tasdiqlash
                                </button>
                            </form>
                            <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#reject-modal">
                                <i class="fas fa-times mr-1"></i> Rad etish
                            </button>
                        </div>
                    </div>

                    @if($datum->submissionMetadata() !== [])
                        <div class="card">
                            <div class="card-header"><h3 class="card-title font-weight-bold">Qo‘shimcha ma’lumotlar</h3></div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    @foreach($datum->submissionMetadata() as $key => $value)
                                        <tr>
                                            <th style="width: 35%">{{ $metadataLabels[$key] ?? $key }}</th>
                                            <td class="text-break">{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </table>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title font-weight-bold">O‘zgarishlar tarixi</h3></div>
                        <div class="card-body">
                            @forelse($datum->histories as $history)
                                <div class="border-bottom pb-3 mb-3">
                                    <div class="d-flex justify-content-between">
                                        <strong class="small">{{ $history->user?->short ?: 'Tizim' }}</strong>
                                        <small class="text-muted">{{ $history->created_at->format('d.m.Y H:i') }}</small>
                                    </div>
                                    <div class="small mt-2 text-break" style="white-space: pre-line">{{ $history->message }}</div>
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

    <div class="modal fade" id="reject-modal" tabindex="-1" role="dialog" aria-labelledby="reject-modal-title" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form method="POST" action="{{ route('reviews.reject', $datum) }}" class="modal-content">
                @csrf
                @method('PATCH')
                <div class="modal-header">
                    <h5 class="modal-title" id="reject-modal-title">Resursni rad etish</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Yopish"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <label for="reason">Rad etish sababi</label>
                    <textarea id="reason" name="reason" rows="5" maxlength="5000" required
                              class="form-control @error('reason') is-invalid @enderror">{{ old('reason') }}</textarea>
                    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Bekor qilish</button>
                    <button type="submit" class="btn btn-danger">Rad etish</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('script')
    @if($errors->has('reason'))
        <script>$('#reject-modal').modal('show');</script>
    @endif
@endsection
