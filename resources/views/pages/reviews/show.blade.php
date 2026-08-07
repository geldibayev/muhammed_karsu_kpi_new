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
        $criterionDescription = preg_replace(
            '/<br\s*\/?>/i',
            "\n",
            (string) data_get($datum->criterion?->desc, 'uz', ''),
        );
        $criterionDescription = trim(strip_tags($criterionDescription));
        $isManualCriterion = $datum->criterion?->checking === 'manual';
        $isAiCriterion = $datum->criterion?->checking === 'ai';
        $isOakArticleCriterion = $datum->criterion?->isOakArticleCriterion() === true;
        $isLaboratoryWorkCriterion = $datum->criterion?->isLaboratoryWorkCriterion() === true;
        $isPrintedLiteratureCriterion = $datum->criterion?->isPrintedEducationalLiteratureCriterion() === true;
        $usesAutomaticAiHumanReviewScore = $datum->criterion?->usesAutomaticAiHumanReviewScore() === true;
        $usesImpactFactorScore = $datum->criterion?->usesImpactFactorAiHumanReviewScore() === true;
        $usesPublicationTierScore = $datum->criterion?->usesPublicationTierAiHumanReviewScore() === true;
        $usesAuthorDividedScore = $datum->criterion?->usesAuthorDividedAiHumanReviewScore() === true;
        $usesUniversityTierScore = $datum->criterion?->usesUniversityTierAiHumanReviewScore() === true;
        $isIndustryFundingCriterion = $datum->criterion?->isIndustryFundingCriterion() === true;
        $isHIndexCriterion = $datum->criterion?->isHIndexCriterion() === true;
        $fixedApprovalOption = $isManualCriterion && $scoreOptions->count() === 1
            && $scoreOptions->first()?->code === \App\Models\CriterionManualScoreOption::FIXED_APPROVAL_CODE
                ? $scoreOptions->first()
                : null;
        $evaluationMaximum = (float) $datum->criterion?->criterionEvaluations
            ->firstWhere('evaluation', $datum->user?->degree)?->score;
        $reviewerPointMaximum = $datum->criterion?->aiSubmissionMaximum($evaluationMaximum) ?? 0;
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

            @if($isAcceptedScoreCorrection ?? false)
                <div class="alert alert-warning">
                    Bu resurs tasdiqlangan. Kiritilgan tekshiruv ma’lumotlari asosida ball serverdagi joriy qoida
                    bo‘yicha qayta hisoblanadi va o‘zgarish tarixga yoziladi.
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
                                @if($criterionDescription !== '')
                                    <dt class="col-sm-4">Baholash qoidasi</dt>
                                    <dd class="col-sm-8 text-break" style="white-space: pre-line">{{ $criterionDescription }}</dd>
                                @endif
                                <dt class="col-sm-4">Resurs yili</dt>
                                <dd class="col-sm-8">{{ $datum->year?->name ?? 'Ko‘rsatilmagan' }}</dd>
                                <dt class="col-sm-4">Yuborilgan vaqt</dt>
                                <dd class="col-sm-8">{{ $datum->created_at->format('d.m.Y H:i:s') }}</dd>
                            </dl>
                        </div>
                        <div class="card-footer d-flex flex-wrap align-items-center">
                            <a href="{{ $reviewReturnUrl ?? route($reviewIndexRoute ?? 'reviews.index') }}" class="btn btn-default btn-sm mr-2">
                                <i class="fas fa-arrow-left mr-1"></i> Ro‘yxatga qaytish
                            </a>
                            @if($isHIndexCriterion)
                                <span class="mr-auto text-muted small">Uchta baza profili tekshiruv uchun berilgan.</span>
                            @elseif($datum->storagePath() !== null)
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

                            @can('transferCriterion', $datum)
                                @if($transferCriteria->isNotEmpty())
                                    <button type="button" class="btn btn-warning btn-sm mr-2"
                                            data-toggle="modal" data-target="#transfer-criterion-modal">
                                        <i class="fas fa-exchange-alt mr-1"></i> Boshqa kriteriyaga o‘tkazish
                                    </button>
                                @else
                                    <button type="button" class="btn btn-warning btn-sm mr-2" disabled
                                            title="O‘tkazish uchun mos kriteriya topilmadi">
                                        <i class="fas fa-exchange-alt mr-1"></i> Boshqa kriteriyaga o‘tkazish
                                    </button>
                                @endif
                            @endcan

                            @if($isHIndexCriterion)
                                <form method="POST" action="{{ route('reviews.approve', $datum) }}" class="mr-2">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-check mr-1"></i> {{ ($isAcceptedScoreCorrection ?? false) ? 'Ballni qayta hisoblash' : 'Tasdiqlash' }}
                                    </button>
                                </form>
                            @elseif($isAiCriterion && $usesAutomaticAiHumanReviewScore)
                                <form method="POST" action="{{ route('reviews.approve', $datum) }}" class="mr-2">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-check mr-1"></i> {{ ($isAcceptedScoreCorrection ?? false) ? 'Ballni qayta hisoblash' : 'Tasdiqlash' }}
                                    </button>
                                </form>
                            @elseif($isAiCriterion)
                                <button type="button" class="btn btn-success btn-sm mr-2"
                                        data-toggle="modal" data-target="#ai-approve-modal">
                                    <i class="fas fa-check mr-1"></i>
                                    @if($isPrintedLiteratureCriterion)
                                        Sahifa va mualliflar bilan tasdiqlash
                                    @elseif($isIndustryFundingCriterion)
                                        Summa va hammualliflar bilan tasdiqlash
                                    @elseif($isOakArticleCriterion || $usesAuthorDividedScore)
                                        Mualliflar soni bilan tasdiqlash
                                    @elseif($usesImpactFactorScore)
                                        Impakt faktor bilan tasdiqlash
                                    @elseif($usesPublicationTierScore)
                                        Kvartil bilan tasdiqlash
                                    @elseif($usesUniversityTierScore)
                                        Universitet Top darajasi bilan tasdiqlash
                                    @else
                                        Ball bilan tasdiqlash
                                    @endif
                                </button>
                            @elseif($isManualCriterion && $scoreOptions->isEmpty())
                                <button type="button" class="btn btn-success btn-sm mr-2" disabled
                                        title="Baholash qoidasi sozlanmagan">
                                    <i class="fas fa-check mr-1"></i> Tasdiqlash
                                </button>
                            @elseif($isManualCriterion && ($educationalContentScoring !== null || $scoreOptions->count() > 1))
                                <button type="button" class="btn btn-success btn-sm mr-2"
                                        data-toggle="modal" data-target="#approve-modal">
                                    <i class="fas fa-check mr-1"></i> Tasdiqlash
                                </button>
                            @else
                                <form method="POST" action="{{ route('reviews.approve', $datum) }}" class="mr-2">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-success btn-sm">
                                        <i class="fas fa-check mr-1"></i>
                                        Tasdiqlash
                                        @if($fixedApprovalOption !== null)
                                            — {{ number_format($fixedApprovalOption->point, 2) }} ball
                                        @endif
                                    </button>
                                </form>
                            @endif
                            @unless($isAcceptedScoreCorrection ?? false)
                                <button type="button" class="btn btn-danger btn-sm" data-toggle="modal" data-target="#reject-modal">
                                    <i class="fas fa-times mr-1"></i> Rad etish
                                </button>
                            @endunless
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

                    @if($isHIndexCriterion)
                        <div class="card">
                            <div class="card-header"><h3 class="card-title font-weight-bold">H-index ma’lumotlari</h3></div>
                            <div class="card-body p-0">
                                <table class="table table-sm table-striped mb-0">
                                    @foreach([
                                        'scopus' => 'Scopus',
                                        'web_of_science' => 'Web of Science',
                                        'research_gate' => 'Research Gate',
                                    ] as $profileKey => $profileLabel)
                                        <tr>
                                            <th style="width: 35%">{{ $profileLabel }}</th>
                                            <td>
                                                <a href="{{ data_get($datum->hIndexProfiles(), $profileKey.'.link') }}" target="_blank" rel="noopener noreferrer">
                                                    Profilni ochish
                                                </a>
                                                <span class="ml-2">h-index: <strong>{{ data_get($datum->hIndexProfiles(), $profileKey.'.value') }}</strong></span>
                                            </td>
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

    @if($isManualCriterion && ($educationalContentScoring !== null || $scoreOptions->count() > 1))
        <div class="modal fade" id="approve-modal" tabindex="-1" role="dialog"
             aria-labelledby="approve-modal-title" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" action="{{ route('reviews.approve', $datum) }}" class="modal-content">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title" id="approve-modal-title">Baholash qoidasini tanlang</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Yopish">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        @if($educationalContentScoring !== null)
                            <div class="alert alert-info py-2">
                                <div><strong>{{ $educationalContentScoring['category'] }}</strong></div>
                                <div class="small">
                                    Maksimal ball: {{ number_format($educationalContentScoring['maximum'], 2) }}.
                                    Ushbu mezonga ko‘pi bilan 3 ta resurs yuklanadi.
                                </div>
                            </div>
                        @endif
                        @if($foreignLanguageCertificateScoring !== null)
                            <div class="alert alert-info py-2">
                                Tekshiruvchi faqat sertifikat darajasini tasdiqlaydi. Ball
                                {{ $foreignLanguageCertificateScoring['special_department']
                                    ? 'Chet tillari fakulteti kafedralari uchun maxsus qoida'
                                    : 'ilmiy daraja va kafedra qoidasi' }}
                                bo‘yicha serverda avtomatik hisoblanadi.
                            </div>
                        @endif
                        <label for="score-option">Tavsifga mos variant</label>
                        <select id="score-option" name="score_option_id" required
                                class="form-control @error('score_option_id') is-invalid @enderror">
                            <option value="">Variantni tanlang</option>
                            @foreach($scoreOptions as $scoreOption)
                                @php
                                    $scorePresentation = data_get(
                                        $educationalContentScoring,
                                        'options.'.$scoreOption->id,
                                    );
                                @endphp
                                <option value="{{ $scoreOption->id }}" @selected(old('score_option_id') == $scoreOption->id)>
                                    {{ data_get($scoreOption->label, 'uz', $scoreOption->code) }}
                                    @if($scorePresentation !== null)
                                        — {{ $scorePresentation['percentage'] }}% = {{ number_format($scorePresentation['point'], 2) }} ball
                                    @elseif($foreignLanguageCertificateScoring !== null)
                                        — {{ number_format((float) $foreignLanguageCertificateScoring['options'][$scoreOption->id], 2) }} ball
                                    @else
                                        — {{ number_format($scoreOption->point, 2) }} ball
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @error('score_option_id')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <div class="small text-muted mt-2">Ball tanlangan qoida bo‘yicha avtomatik hisoblanadi.</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-default" data-dismiss="modal">Bekor qilish</button>
                        <button type="submit" class="btn btn-success">Tasdiqlash</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    @if($isAiCriterion && ! $usesAutomaticAiHumanReviewScore)
        <div class="modal fade" id="ai-approve-modal" tabindex="-1" role="dialog"
             aria-labelledby="ai-approve-modal-title" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" action="{{ route('reviews.approve', $datum) }}" class="modal-content">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title" id="ai-approve-modal-title">AI tekshiruvidan qolgan resursni baholash</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Yopish">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        @if($isPrintedLiteratureCriterion)
                            <div class="form-group">
                                <label for="page-count">Kitobdagi jami sahifalar soni</label>
                                <input id="page-count" name="page_count" type="number" min="1" max="100000"
                                       step="1" required value="{{ old('page_count', $datum->page_count) }}"
                                       class="form-control @error('page_count') is-invalid @enderror">
                                @error('page_count')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="author-count">Kitobdagi jami mualliflar soni</label>
                                <input id="author-count" name="author_count" type="number" min="1" max="1000"
                                       step="1" required value="{{ old('author_count', $datum->author_count ?? data_get($datum->material, 'article.authors_num')) }}"
                                       class="form-control @error('author_count') is-invalid @enderror">
                                @error('author_count')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="small text-muted mt-2">
                                Ball serverda: sahifalar / 16 × {{ $datum->criterion?->code === '1.2' ? '0.4' : '0.3' }} / mualliflar soni.
                            </div>
                        @elseif($isIndustryFundingCriterion)
                            <div class="form-group">
                                <label for="received-amount">Universitet hisobiga tushgan summa (so‘m)</label>
                                <input id="received-amount" name="received_amount" type="number" min="0.01"
                                       max="9999999999999999.99" step="0.01" required
                                       value="{{ old('received_amount', $datum->received_amount) }}"
                                       class="form-control @error('received_amount') is-invalid @enderror">
                                @error('received_amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="form-group">
                                <label for="author-count">Jami hammualliflar soni</label>
                                <input id="author-count" name="author_count" type="number" min="1" max="1000"
                                       step="1" required value="{{ old('author_count', $datum->author_count) }}"
                                       class="form-control @error('author_count') is-invalid @enderror">
                                @error('author_count')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="small text-muted mt-2">
                                Ball serverda: tushgan summa / 1 000 000 / hammualliflar soni.
                            </div>
                        @elseif($isOakArticleCriterion)
                            <label for="author-count">Maqoladagi jami mualliflar soni</label>
                            <input id="author-count" name="author_count" type="number" min="1" max="1000"
                                   step="1" required value="{{ old('author_count', data_get($datum->material, 'article.authors_num')) }}"
                                   class="form-control @error('author_count') is-invalid @enderror">
                            @error('author_count')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="small text-muted mt-2">
                                Bazaviy {{ number_format($oakArticleBasePoint, 2) }} ball kiritilgan mualliflar soniga avtomatik bo‘linadi.
                            </div>
                        @elseif($usesAuthorDividedScore)
                            <label for="author-count">{{ $isLaboratoryWorkCriterion ? 'Resursdagi jami mualliflar soni' : 'Patentdagi jami mualliflar soni' }}</label>
                            <input id="author-count" name="author_count" type="number" min="1" max="1000"
                                   step="1" required value="{{ old('author_count', $datum->author_count) }}"
                                   class="form-control @error('author_count') is-invalid @enderror">
                            @error('author_count')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="small text-muted mt-2">
                                Bazaviy {{ number_format($isLaboratoryWorkCriterion ? \App\Support\LaboratoryWorkCriterionRule::BASE_POINT : $evaluationMaximum, 2) }} ball mualliflar soniga avtomatik bo‘linadi.
                            </div>
                        @elseif($usesImpactFactorScore)
                            <label for="impact-factor">Jurnalning impakt faktori</label>
                            <input id="impact-factor" name="impact_factor" type="number" min="1" max="1000"
                                   step="1" required value="{{ old('impact_factor', $datum->impact_factor) }}"
                                   class="form-control @error('impact_factor') is-invalid @enderror">
                            @error('impact_factor')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="small text-muted mt-2">
                                Har bir birlik uchun maksimal ballning 10 foizi, 10 va undan yuqori qiymatda to‘liq ball beriladi.
                            </div>
                        @elseif($usesPublicationTierScore)
                            <label for="publication-tier">Jurnal kvartili yoki nashr turi</label>
                            <select id="publication-tier" name="publication_tier" required
                                    class="form-control @error('publication_tier') is-invalid @enderror">
                                <option value="">Tanlang</option>
                                <option value="q1" @selected(old('publication_tier', $datum->publication_tier) === 'q1')>Q1 — 20 ball</option>
                                <option value="q2" @selected(old('publication_tier', $datum->publication_tier) === 'q2')>Q2 — 15 ball</option>
                                <option value="q3" @selected(old('publication_tier', $datum->publication_tier) === 'q3')>Q3 — 10 ball</option>
                                <option value="q4" @selected(old('publication_tier', $datum->publication_tier) === 'q4')>Q4 — 5 ball</option>
                                <option value="conference" @selected(old('publication_tier', $datum->publication_tier) === 'conference')>Scopus/WoS konferensiya materiali — 5 ball</option>
                            </select>
                            @error('publication_tier')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        @elseif($usesUniversityTierScore)
                            <label for="university-tier">Universitetning xalqaro reytingdagi Top darajasi</label>
                            <select id="university-tier" name="university_tier" required
                                    class="form-control @error('university_tier') is-invalid @enderror">
                                <option value="">Tanlang</option>
                                @foreach([
                                    'top_100' => 'Top-100',
                                    'top_300' => 'Top-101–300',
                                    'top_500' => 'Top-301–500',
                                    'top_1000' => 'Top-501–1000',
                                ] as $universityTier => $universityTierLabel)
                                    @php
                                        $universityTierPoint = $datum->criterion?->isProfessionalDevelopmentCriterion()
                                            ? \App\Support\ProfessionalDevelopmentCriterionRule::pointForUniversityTier(
                                                $evaluationMaximum,
                                                $universityTier,
                                            )
                                            : \App\Support\InternationalCooperationCriterionRule::pointForUniversityTier(
                                                $evaluationMaximum,
                                                $universityTier,
                                            );
                                    @endphp
                                    <option value="{{ $universityTier }}"
                                        @selected(old('university_tier', $datum->university_tier) === $universityTier)>
                                        {{ $universityTierLabel }}
                                        @if($universityTierPoint !== null)
                                            — {{ number_format($universityTierPoint, 2) }} ball
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('university_tier')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="small text-muted mt-2">
                                Ball foydalanuvchining baholash toifasi va tanlangan Top darajasi bo‘yicha avtomatik hisoblanadi.
                            </div>
                        @else
                            <label for="reviewer-point">Tasdiqlangan ball</label>
                            <input id="reviewer-point" name="point" type="number" min="0"
                                   max="{{ $reviewerPointMaximum }}" step="0.01" required
                                   value="{{ old('point') }}"
                                   class="form-control @error('point') is-invalid @enderror">
                            @error('point')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <div class="small text-muted mt-2">
                                Ruxsat etilgan oraliq: 0–{{ number_format($reviewerPointMaximum, 2) }} ball.
                            </div>
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

    @can('transferCriterion', $datum)
    @if($transferCriteria->isNotEmpty())
        <div class="modal fade" id="transfer-criterion-modal" tabindex="-1" role="dialog"
             aria-labelledby="transfer-criterion-modal-title" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <form method="POST" action="{{ route('reviews.transfer-criterion', $datum) }}" class="modal-content">
                    @csrf
                    @method('PATCH')
                    <div class="modal-header">
                        <h5 class="modal-title" id="transfer-criterion-modal-title">Boshqa kriteriyaga o‘tkazish</h5>
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

    @unless($isAcceptedScoreCorrection ?? false)
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
    @endunless
@endsection

@section('script')
    @if($errors->has('point') || $errors->has('author_count') || $errors->has('page_count') || $errors->has('impact_factor') || $errors->has('publication_tier') || $errors->has('university_tier') || $errors->has('received_amount'))
        <script>$('#ai-approve-modal').modal('show');</script>
    @elseif($errors->has('criterion_id'))
        <script>$('#transfer-criterion-modal').modal('show');</script>
    @elseif($errors->has('score_option_id'))
        <script>$('#approve-modal').modal('show');</script>
    @elseif($errors->has('reason'))
        <script>$('#reject-modal').modal('show');</script>
    @endif
@endsection
