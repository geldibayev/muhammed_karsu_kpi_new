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
        $publicationTierLabels = [
            'q1' => 'Q1 — 20 ball',
            'q2' => 'Q2 — 15 ball',
            'q3' => 'Q3 — 10 ball',
            'q4' => 'Q4 — 5 ball',
            'conference' => 'Scopus/WoS konferensiya materiali — 5 ball',
        ];
    @endphp

    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <h3 class="card-title font-weight-bold">Resurs #{{ $datum->id }}</h3>
                            <div class="d-flex align-items-center">
                                @if($finalConfirmation !== null)
                                    <span class="badge badge-warning px-3 py-2 mr-2"
                                          title="Yakuniy tekshiruv tasdiqlangan">
                                        <i class="fas fa-star mr-1" aria-hidden="true"></i>
                                        Yakuniy tasdiq
                                    </span>
                                @endif
                                <span class="badge {{ $status->badgeClass() }} px-3 py-2">
                                    {{ $status->label() }}
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            @if($errors->any())
                                <div class="alert alert-danger" role="alert">
                                    <div class="font-weight-bold">Amalni bajarishda xatolik:</div>
                                    <ul class="mb-0 pl-3">
                                        @foreach($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif

                            @if($finalConfirmation !== null)
                                <div class="alert alert-warning" role="status">
                                    <div class="font-weight-bold">
                                        <i class="fas fa-star mr-1" aria-hidden="true"></i>
                                        Yakuniy tekshiruv tasdiqlangan
                                    </div>
                                    <div class="small mt-1">
                                        Mas’ul:
                                        <strong>{{ $finalConfirmation->user?->full ?: ($finalConfirmation->user?->short ?: 'Noma’lum mas’ul') }}</strong>
                                        · Tasdiqlangan vaqt:
                                        <strong>{{ $finalConfirmation->created_at->format('d.m.Y H:i:s') }}</strong>
                                    </div>
                                </div>
                            @endif

                            @if($status === \App\Enums\DatumStatus::Cancelled)
                                @php($latestCancellationReason = $datum->histories->firstWhere('type', 'error')?->messageForSubmitter() ?? $datum->reason)
                                <div class="alert alert-danger" role="alert">
                                    <h4 class="h6 font-weight-bold">
                                        <i class="fas fa-exclamation-triangle mr-1" aria-hidden="true"></i>
                                        Resursning qaytarilish sababi
                                    </h4>
                                    <div class="text-break" style="white-space: pre-line;">{{ $latestCancellationReason ?: 'Qaytarilish sababi ko‘rsatilmagan.' }}</div>
                                </div>
                            @endif

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

                                @if($datum->criterion?->code === \App\Support\EducationalContentCriterionRule::CODE)
                                    <dt class="col-sm-4">1.1 resurs turi</dt>
                                    <dd class="col-sm-8">
                                        {{ data_get($datum->manualScoreOption?->label, 'uz', 'Hali belgilanmagan') }}
                                        @if($educationalContentTypeDuplicate)
                                            <span class="badge badge-warning ml-1">Takrorlangan toifa</span>
                                        @endif
                                    </dd>
                                @endif

                                <dt class="col-sm-4">Tekshiruv xulosasi</dt>
                                <dd class="col-sm-8 text-break" style="white-space: pre-line;">{{ $datum->reason ?: 'Xulosa hali mavjud emas.' }}</dd>
                            </dl>
                        </div>
                        <div class="card-footer">
                            <a href="{{ $datum->user_id === auth()->id() ? route('files.show', $status) : route('ratings.show', $datum->user_id) }}"
                               class="btn btn-default btn-sm">
                                <i class="fas fa-arrow-left mr-1"></i> Ro‘yxatga qaytish
                            </a>

                            @can('replaceFourOneOneReference', $datum)
                                <a href="{{ route('upload.four-one-one-reference.replace', $datum) }}"
                                   class="btn btn-primary btn-sm ml-2">
                                    <i class="fas fa-plus mr-1" aria-hidden="true"></i>
                                    Yangi resurs yuklash
                                </a>
                            @endcan

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

                            @can('review', $datum)
                                <a href="{{ route('reviews.show', $datum) }}"
                                   class="btn btn-success btn-sm ml-2">
                                    <i class="fas fa-user-check mr-1" aria-hidden="true"></i>
                                    Resursni baholash
                                </a>
                            @endcan

                            @can('correctAcceptedScore', $datum)
                                <a href="{{ route('reviews.show', $datum) }}"
                                   class="btn btn-warning btn-sm ml-2">
                                    <i class="fas fa-calculator mr-1" aria-hidden="true"></i>
                                    Ballni to‘g‘rilash
                                </a>
                            @endcan

                            @can('updateAcceptedScore', $datum)
                                <button type="button" class="btn btn-warning btn-sm ml-2"
                                        data-toggle="modal" data-target="#update-accepted-score-modal"
                                        @disabled($decisionOverridePointMaximum === null)>
                                    <i class="fas fa-calculator mr-1" aria-hidden="true"></i>
                                    Ballni o‘zgartirish
                                </button>
                            @endcan

                            @can('confirmFinalReview', $datum)
                                <form method="POST" action="{{ route('submissions.final-confirmation.update', $datum) }}"
                                      class="d-inline-block ml-2">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-warning btn-sm"
                                            onclick="return confirm('Resurs yakuniy tekshiruvdan o‘tganini tasdiqlaysizmi?')">
                                        <i class="fas fa-star mr-1" aria-hidden="true"></i>
                                        Oxirgi tekshiruvdan o‘tdi
                                    </button>
                                </form>
                            @endcan

                            @can('overrideAcceptance', $datum)
                                <button type="button" class="btn btn-danger btn-sm ml-2"
                                        data-toggle="modal" data-target="#reject-accepted-ai-modal">
                                    <i class="fas fa-user-times mr-1"></i>
                                    Tasdiqlangan resursni rad etish
                                </button>

                                @if($datum->criterion?->supportsSharedResourceMatching())
                                    <button type="button" class="btn btn-info btn-sm ml-2"
                                            data-toggle="collapse" data-target="#matching-shared-resource-submissions"
                                            aria-expanded="false" aria-controls="matching-shared-resource-submissions">
                                        <i class="fas fa-users mr-1" aria-hidden="true"></i>
                                        Boshqa yuklamalar
                                        <span class="badge badge-light ml-1">{{ $matchingSharedResourceSubmissions->count() }}</span>
                                    </button>
                                @endif
                            @endcan

                            @can('changeEducationalContentType', $datum)
                                <button type="button" class="btn btn-warning btn-sm ml-2"
                                        data-toggle="modal" data-target="#change-educational-content-type-modal"
                                        @disabled($educationalContentTypeOptions->isEmpty())>
                                    <i class="fas fa-tags mr-1"></i>
                                    Resurs turini o‘zgartirish
                                </button>
                            @endcan

                            @can('transferCriterion', $datum)
                                @if($transferCriteria->isNotEmpty())
                                    <button type="button" class="btn btn-warning btn-sm ml-2"
                                            data-toggle="modal" data-target="#transfer-criterion-modal">
                                        <i class="fas fa-exchange-alt mr-1"></i>
                                        Boshqa kriteriyaga o‘tkazish
                                    </button>
                                @else
                                    <button type="button" class="btn btn-warning btn-sm ml-2" disabled
                                            title="O‘tkazish uchun mos kriteriya topilmadi">
                                        <i class="fas fa-exchange-alt mr-1"></i>
                                        Boshqa kriteriyaga o‘tkazish
                                    </button>
                                @endif
                            @endcan

                            @can('overrideCancellation', $datum)
                              @if($decisionOverridePointMaximum !== null
                                  && ($datum->criterion?->code !== \App\Support\EducationalContentCriterionRule::CODE
                                      || $educationalContentTypeOptions->isNotEmpty())
                                  && ($datum->criterion?->code !== \App\Support\ForeignLanguageCertificateCriterionRule::CODE
                                      || $foreignLanguageCertificateOptions->isNotEmpty()))
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

                        @can('overrideAcceptance', $datum)
                            @if($datum->criterion?->supportsSharedResourceMatching())
                                <div class="collapse border-top" id="matching-shared-resource-submissions">
                                    <div class="card-body">
                                        @forelse($matchingSharedResourceSubmissions as $matchingDatum)
                                            <div class="border-bottom pb-2 mb-2">
                                                <div class="d-flex flex-wrap align-items-center justify-content-between">
                                                    <div>
                                                        <strong>#{{ $matchingDatum->id }} — {{ $matchingDatum->name }}</strong>
                                                        <span class="badge {{ \App\Enums\DatumStatus::from($matchingDatum->status)->badgeClass() }} ml-1">
                                                            {{ \App\Enums\DatumStatus::from($matchingDatum->status)->label() }}
                                                        </span>
                                                        @if($matchingDatum->isFinalReviewConfirmed())
                                                            <i class="fas fa-star text-warning ml-1"
                                                               title="Yakuniy tekshiruv tasdiqlangan"
                                                               aria-label="Yakuniy tekshiruv tasdiqlangan"></i>
                                                        @endif
                                                        <div class="small text-muted">
                                                            {{ $matchingDatum->user?->full ?: $matchingDatum->user?->short ?: 'Noma’lum' }}
                                                            ({{ $matchingDatum->user?->hemis_id ?? 'HEMIS ID yo‘q' }})
                                                            · {{ number_format((float) $matchingDatum->point, 2) }} ball
                                                        </div>
                                                    </div>
                                                    @can('view', $matchingDatum)
                                                        <a href="{{ route('upload.details', $matchingDatum) }}"
                                                           class="btn btn-outline-primary btn-sm">
                                                            Ko‘rish
                                                        </a>
                                                    @endcan
                                                </div>
                                            </div>
                                        @empty
                                            <div class="alert alert-info mb-0">
                                                Bu resursni boshqa foydalanuvchilar yuklamagan.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        @endcan
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

                    @if($datum->criterion?->isHIndexCriterion())
                        <div class="card">
                            <div class="card-header">
                                <h3 class="card-title font-weight-bold">H-index ma’lumotlari</h3>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <tbody>
                                        @foreach(\App\Actions\CorrectHIndexProfileValue::PROFILES as $profileKey => $profileLabel)
                                            @php($profile = data_get($datum->hIndexProfiles(), $profileKey, []))
                                            <tr>
                                                <th style="width: 25%;">{{ $profileLabel }}</th>
                                                <td>
                                                    @if(filled(data_get($profile, 'link')))
                                                        <a href="{{ data_get($profile, 'link') }}" target="_blank" rel="noopener noreferrer">Profilni ochish</a>
                                                    @else
                                                        <span class="text-muted">Profil kiritilmagan</span>
                                                    @endif
                                                </td>
                                                <td style="width: 40%;">
                                                    @can('updateHIndexProfile', $datum)
                                                    @if(is_numeric(data_get($profile, 'value')) && filled(data_get($profile, 'link')))
                                                        <form method="POST" action="{{ route('submissions.h-index-profile.update', $datum) }}"
                                                              class="form-inline justify-content-end">
                                                            @csrf
                                                            @method('PATCH')
                                                            <input type="hidden" name="profile" value="{{ $profileKey }}">
                                                            <input type="hidden" name="expected_value" value="{{ data_get($profile, 'value', 0) }}">
                                                            <label class="sr-only" for="h-index-{{ $profileKey }}">{{ $profileLabel }} H-index</label>
                                                            <input id="h-index-{{ $profileKey }}" name="new_value" type="number" min="0" step="1" required
                                                                   value="{{ old('profile') === $profileKey ? old('new_value') : data_get($profile, 'value') }}"
                                                                   class="form-control form-control-sm mr-2 @if(old('profile') === $profileKey) @error('new_value') is-invalid @enderror @endif"
                                                                   style="width: 90px;">
                                                            <button type="submit" class="btn btn-warning btn-sm">Saqlash</button>
                                                        </form>
                                                        @if(old('profile') === $profileKey)
                                                            @error('new_value')<div class="text-danger small mt-1 text-right">{{ $message }}</div>@enderror
                                                        @endif
                                                    @else
                                                        <span class="text-muted">Tahrirlash uchun profil havolasi va qiymati kerak</span>
                                                    @endif
                                                    @else
                                                        <span>h-index: <strong>{{ data_get($profile, 'value', '—') }}</strong></span>
                                                    @endcan
                                                </td>
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

            @can('updateAcceptedScore', $datum)
                @if($decisionOverridePointMaximum !== null)
                    <div class="modal fade" id="update-accepted-score-modal" tabindex="-1" role="dialog"
                         aria-labelledby="update-accepted-score-modal-title" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <form method="POST" action="{{ route('submissions.accepted-score.update', $datum) }}"
                                  class="modal-content">
                                @csrf
                                @method('PATCH')
                                <div class="modal-header">
                                    <h5 class="modal-title" id="update-accepted-score-modal-title">
                                        Tasdiqlangan resurs ballini o‘zgartirish
                                    </h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Yopish">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    @if($datum->criterion?->usesPublicationTierAiHumanReviewScore())
                                        <div class="form-group">
                                            <label for="updated-publication-tier">Jurnal kvartili yoki nashr turi</label>
                                            <select id="updated-publication-tier" name="publication_tier" required
                                                    class="form-control @error('publication_tier') is-invalid @enderror">
                                                <option value="">Tanlang</option>
                                                @foreach($publicationTierLabels as $publicationTier => $publicationTierLabel)
                                                    <option value="{{ $publicationTier }}"
                                                        @selected(old('publication_tier', $datum->publication_tier) === $publicationTier)>
                                                        {{ $publicationTierLabel }}
                                                    </option>
                                                @endforeach
                                            </select>
                                            <small class="form-text text-muted">Ball tanlangan kvartil bo‘yicha serverda hisoblanadi.</small>
                                            @error('publication_tier')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                        </div>
                                    @elseif($datum->criterion?->usesDegreeBasedAuthorDividedArticleScore())
                                    <div class="form-group">
                                        <label for="updated-author-count">Maqoladagi jami mualliflar soni</label>
                                        <input id="updated-author-count" name="author_count" type="number" min="1"
                                               max="1000" step="1" required
                                               value="{{ old('author_count', $datum->author_count) }}"
                                               class="form-control @error('author_count') is-invalid @enderror">
                                        <small class="form-text text-muted">
                                            Ball ilmiy darajaga qarab 0.5 yoki 0.75 ni mualliflar soniga bo‘lib hisoblanadi.
                                        </small>
                                        @error('author_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    @else
                                    <div class="form-group">
                                        <label for="updated-point">Yangi ball</label>
                                        <input id="updated-point" name="point" type="number" min="0"
                                               max="{{ $decisionOverridePointMaximum }}" step="0.0001" required
                                               value="{{ old('point', $datum->point) }}"
                                               class="form-control @error('point') is-invalid @enderror">
                                        <small class="form-text text-muted">
                                            Ruxsat etilgan oraliq: 0–{{ number_format($decisionOverridePointMaximum, 4, '.', '') }}
                                        </small>
                                        @error('point')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                    @endif
                                    <div class="form-group mb-0">
                                        <label for="updated-point-reason">O‘zgartirish sababi</label>
                                        <textarea id="updated-point-reason" name="score_change_reason" rows="4" maxlength="5000"
                                                  required class="form-control @error('score_change_reason') is-invalid @enderror">{{ old('score_change_reason') }}</textarea>
                                        @error('score_change_reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Bekor qilish</button>
                                    <button type="submit" class="btn btn-warning">Ballni saqlash</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan

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

            @can('changeEducationalContentType', $datum)
                @if($educationalContentTypeOptions->isNotEmpty())
                    <div class="modal fade" id="change-educational-content-type-modal" tabindex="-1" role="dialog"
                         aria-labelledby="change-educational-content-type-modal-title" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <form method="POST" action="{{ route('upload.educational-content-type.update', $datum) }}"
                                  class="modal-content">
                                @csrf
                                @method('PATCH')
                                <div class="modal-header">
                                    <h5 class="modal-title" id="change-educational-content-type-modal-title">
                                        1.1 resurs turini o‘zgartirish
                                    </h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Yopish">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    @if($educationalContentTypeDuplicate)
                                        <div class="alert alert-warning py-2">
                                            Bu toifa foydalanuvchining boshqa tasdiqlangan resursida ham bor.
                                            Bo‘sh toifalardan birini tanlang.
                                        </div>
                                    @endif
                                    <label for="accepted-educational-content-type">Resurs turi</label>
                                    <select id="accepted-educational-content-type" name="score_option_id" required
                                            class="form-control @error('score_option_id') is-invalid @enderror">
                                        <option value="">Tanlang</option>
                                        @foreach($educationalContentTypeOptions as $scoreOption)
                                            <option value="{{ $scoreOption->id }}"
                                                @selected(old('score_option_id', $datum->manual_score_option_id) == $scoreOption->id)>
                                                {{ data_get($scoreOption->label, 'uz', $scoreOption->code) }}
                                                — {{ \App\Support\EducationalContentCriterionRule::percentageFor($scoreOption->code) }}%
                                                = {{ number_format((float) \App\Support\EducationalContentCriterionRule::pointFor((float) $educationalContentMaximum, $scoreOption->code), 2) }} ball
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('score_option_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Bekor qilish</button>
                                    <button type="submit" class="btn btn-warning">Saqlash va ballni hisoblash</button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
            @endcan

            @can('transferCriterion', $datum)
                @if($transferCriteria->isNotEmpty())
                    <div class="modal fade" id="transfer-criterion-modal" tabindex="-1" role="dialog"
                         aria-labelledby="transfer-criterion-modal-title" aria-hidden="true">
                        <div class="modal-dialog" role="document">
                            <form method="POST" action="{{ route('reviews.transfer-criterion', $datum) }}"
                                  class="modal-content">
                                @csrf
                                @method('PATCH')
                                <div class="modal-header">
                                    <h5 class="modal-title" id="transfer-criterion-modal-title">
                                        Boshqa kriteriyaga o‘tkazish
                                    </h5>
                                    <button type="button" class="close" data-dismiss="modal" aria-label="Yopish">
                                        <span aria-hidden="true">&times;</span>
                                    </button>
                                </div>
                                <div class="modal-body">
                                    <div class="alert alert-warning small">
                                        Resurs yangi kriteriyada tekshirilayotgan holatga o‘tadi va balli 0 ga qaytariladi.
                                    </div>
                                    <label for="transfer-criterion">Yangi kriteriya</label>
                                    <select id="transfer-criterion" name="criterion_id" required
                                            class="form-control @error('criterion_id') is-invalid @enderror">
                                        <option value="">Kriteriyani tanlang</option>
                                        @foreach($transferCriteria as $transferCriterion)
                                            <option value="{{ $transferCriterion->id }}"
                                                @selected(old('criterion_id') == $transferCriterion->id)>
                                                {{ data_get($transferCriterion->parent?->name, 'uz', 'Bo‘limsiz') }}
                                                / {{ data_get($transferCriterion->name, 'uz', 'Nomsiz kriteriya') }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('criterion_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-default" data-dismiss="modal">Bekor qilish</button>
                                    <button type="submit" class="btn btn-warning">
                                        <i class="fas fa-exchange-alt mr-1"></i> O‘tkazish
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                @endif
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
                                @if($datum->criterion?->code === \App\Support\EducationalContentCriterionRule::CODE)
                                    <div class="alert alert-info py-2">
                                        Faqat foydalanuvchi hali ishlatmagan turlar ko‘rsatiladi.
                                        Ball tanlangan tur bo‘yicha avtomatik hisoblanadi.
                                    </div>
                                    <label for="cancelled-educational-content-type">Resurs turi</label>
                                    <select id="cancelled-educational-content-type" name="score_option_id" required
                                            class="form-control @error('score_option_id') is-invalid @enderror">
                                        <option value="">Tanlang</option>
                                        @foreach($educationalContentTypeOptions as $scoreOption)
                                            <option value="{{ $scoreOption->id }}" @selected(old('score_option_id') == $scoreOption->id)>
                                                {{ data_get($scoreOption->label, 'uz', $scoreOption->code) }}
                                                — {{ \App\Support\EducationalContentCriterionRule::percentageFor($scoreOption->code) }}%
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('score_option_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @elseif($datum->criterion?->code === \App\Support\ForeignLanguageCertificateCriterionRule::CODE)
                                    <div class="alert alert-info py-2">
                                        Faqat sertifikat darajasini tanlang. Ball kafedra va ilmiy daraja bo‘yicha
                                        serverda avtomatik hisoblanadi.
                                    </div>
                                    <label for="cancelled-foreign-language-level">Sertifikat darajasi</label>
                                    <select id="cancelled-foreign-language-level" name="score_option_id" required
                                            class="form-control @error('score_option_id') is-invalid @enderror">
                                        <option value="">Tanlang</option>
                                        @foreach($foreignLanguageCertificateOptions as $scoreOption)
                                            <option value="{{ $scoreOption->id }}" @selected(old('score_option_id') == $scoreOption->id)>
                                                {{ data_get($scoreOption->label, 'uz', $scoreOption->code) }}
                                                — {{ number_format((float) ($foreignLanguageCertificatePoints[$scoreOption->id] ?? 0), 2) }} ball
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('score_option_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @elseif($datum->criterion?->usesPublicationTierAiHumanReviewScore())
                                    <div class="alert alert-info py-2">
                                        Ball tanlangan kvartil bo‘yicha serverda avtomatik hisoblanadi.
                                    </div>
                                    <label for="cancelled-publication-tier">Jurnal kvartili yoki nashr turi</label>
                                    <select id="cancelled-publication-tier" name="publication_tier" required
                                            class="form-control @error('publication_tier') is-invalid @enderror">
                                        <option value="">Tanlang</option>
                                        @foreach($publicationTierLabels as $publicationTier => $publicationTierLabel)
                                            <option value="{{ $publicationTier }}" @selected(old('publication_tier') === $publicationTier)>
                                                {{ $publicationTierLabel }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('publication_tier')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @elseif($datum->criterion?->usesDegreeBasedAuthorDividedArticleScore())
                                    <div class="alert alert-info py-2">
                                        {{ \App\Support\OakArticleCriterionRule::DESCRIPTION_UZ }}
                                    </div>
                                    <label for="cancelled-oak-author-count">Jami mualliflar soni</label>
                                    <input id="cancelled-oak-author-count" type="number" name="author_count"
                                           min="1" max="1000" step="1" required
                                           value="{{ old('author_count', $datum->author_count ?? data_get($datum->material, 'article.authors_num')) }}"
                                           class="form-control @error('author_count') is-invalid @enderror">
                                    @error('author_count')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                @else
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
                                @endif
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-default" data-dismiss="modal">Bekor qilish</button>
                                <button type="submit" class="btn btn-success">Tasdiqlash</button>
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
    @if(($errors->has('point') || $errors->has('author_count') || $errors->has('publication_tier') || $errors->has('score_change_reason'))
        && $status === \App\Enums\DatumStatus::Accepted
        && auth()->user()?->can('updateAcceptedScore', $datum))
        <script>$('#update-accepted-score-modal').modal('show');</script>
    @elseif($errors->has('score_option_id') && $status === \App\Enums\DatumStatus::Accepted)
        <script>$('#change-educational-content-type-modal').modal('show');</script>
    @elseif($errors->has('criterion_id'))
        <script>$('#transfer-criterion-modal').modal('show');</script>
    @elseif($errors->has('point') || $errors->has('author_count') || $errors->has('publication_tier') || ($errors->has('score_option_id') && $status === \App\Enums\DatumStatus::Cancelled))
        <script>$('#approve-cancelled-ai-modal').modal('show');</script>
    @elseif($errors->has('reason'))
        <script>$('#reject-accepted-ai-modal').modal('show');</script>
    @endif
@endsection
