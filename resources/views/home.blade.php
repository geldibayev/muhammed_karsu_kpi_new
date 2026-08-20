@extends('layouts.app')

@section('content')
    @php($criterionSectionTabLabels = ['O‘quv-uslubiy ishlar', 'Xalqaro faoliyat', 'Ilmiy-innovatsion ishlar', 'Ma’naviy faoliyat'])

    <section class="content">
        @unless($resourceUploadsEnabled)
            <div class="alert alert-warning shadow-sm">
                <i class="fas fa-pause-circle mr-1" aria-hidden="true"></i>
                {{ $resourceUploadWindowOpen
                    ? 'Tizimga resurs yuklash administrator tomonidan vaqtincha to‘xtatilgan.'
                    : 'Resurs yuklash muddati yakunlangan. Yangi resurslar qabul qilinmaydi.' }}
            </div>
        @endunless

        <div class="callout callout-warning bg-light">
            <div class="d-flex align-items-center">
                <i class="fas fa-star fa-2x text-warning mr-3" aria-hidden="true"></i>
                <div>
                    <h5 class="font-weight-bold mb-1">Asosiy indikatorlar</h5>
                    <p class="mb-0">
                        Mezonlarning belgilangan ballari bo‘yicha jami 100 ballgacha to‘plash mumkin.
                        Yulduzcha bilan ajratilgan asosiy indikatorlarda ko‘rsatilgan qiymat
                        <span class="badge badge-warning">minimal ball</span>
                        bo‘lib, maksimal ball chegaralanmagan. Yuqori natija uchun ushbu indikatorlardan
                        ko‘proq ball olish mumkin.
                    </p>
                </div>
            </div>
        </div>

        <div class="card card-outline card-warning">
            <div class="card-header p-0 pt-1 border-bottom-0">
                <ul class="nav nav-tabs nav-fill flex-column flex-md-row" role="tablist">
                    @foreach($criteria as $item)
                        <li class="nav-item" role="presentation">
                            <a id="criterion-section-tab-{{ $item->getKey() }}"
                               @class(['nav-link', 'active' => $loop->first])
                               data-toggle="tab"
                               data-testid="criterion-section-tab"
                               href="#criterion-section-{{ $item->getKey() }}"
                               role="tab"
                               aria-controls="criterion-section-{{ $item->getKey() }}"
                               aria-selected="{{ $loop->first ? 'true' : 'false' }}">
                                <span class="badge badge-light mr-1">{{ $loop->iteration }}</span>
                                {{ $criterionSectionTabLabels[$loop->index] ?? data_get($item->name, 'uz', 'Nomsiz bo\'lim') }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
            <div class="card-body p-0">
                <div class="tab-content">
                    @foreach($criteria as $item)
                        <div id="criterion-section-{{ $item->getKey() }}"
                             @class(['tab-pane fade', 'show active' => $loop->first])
                             data-testid="criterion-section-pane"
                             role="tabpanel"
                             aria-labelledby="criterion-section-tab-{{ $item->getKey() }}">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead>
                                    <tr>
                                        <th class="text-center" style="width: 5%;">#</th>
                                        <th>Mezon</th>
                                        <th class="text-center" style="width: 20%;">Masʼul</th>
                                        <th class="text-center" style="width: 15%;">Baholash usuli</th>
                                        <th class="text-center" style="width: 10%;">Ball</th>
                                        <th class="text-center" style="width: 10%;">Amallar</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @forelse($item->children as $value)
                                        @php($evaluation = $value->criterionEvaluations->first())
                                        @php($isPrimaryIndicator = $value->isPrimaryIndicator())
                                        <tr @class(['small', 'table-warning' => $isPrimaryIndicator])
                                            @if($isPrimaryIndicator) data-testid="primary-indicator-row" @endif>
                                            <td class="align-middle text-center">
                                                {{ $value->code ?: $loop->parent->iteration.'/'.$value->id }}
                                            </td>
                                            <td class="align-middle">
                                                @if($isPrimaryIndicator)
                                                    <span class="badge badge-warning mb-2 px-2 py-1">
                                                        <i class="fas fa-star mr-1" aria-hidden="true"></i>
                                                        Asosiy indikator
                                                    </span>
                                                @endif
                                                <div class="font-weight-bold" style="text-align: justify">
                                                    {{ data_get($value->name, 'uz', 'Nomsiz mezon') }}
                                                </div>
                                                @php($description = strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", data_get($value->desc, 'uz', ''))))
                                                <div style="text-align: justify; white-space: pre-line">{{ $description }}</div>
                                            </td>
                                            <td class="text-center align-middle">
                                                @if($value->checking == 'ai')
                                                    <div>
                                                        <i class="fa fa-robot mr-1"></i>
                                                        Sunʼiy intellekt
                                                    </div>
                                                @endif
                                                @if($value->reviewerAssignment)
                                                    <div @class(['mt-1' => $value->checking == 'ai'])>
                                                        <i class="fa fa-user-check mr-1"></i>
                                                        {{ $value->reviewerAssignment->user?->full
                                                            ?: ($value->reviewerAssignment->user?->short
                                                                ?: 'HEMIS ID: '.$value->reviewerAssignment->hemis_id) }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="align-middle text-center">
                                                <x-rating-method
                                                    :method="$ratingMethods->get($value->getKey())"
                                                    :criterion="$value"
                                                />
                                            </td>
                                            <td class="align-middle text-center">
                                                <div class="text-nowrap">
                                                    <span class="font-weight-bold text-success">
                                                        {{ number_format($points->get($value->id, 0), 2) }}
                                                    </span>
                                                    /
                                                    <span class="font-weight-bold text-primary">
                                                        {{ number_format($evaluation?->score ?? 0, 2) }}
                                                    </span>
                                                </div>
                                                @if($isPrimaryIndicator)
                                                    <span class="badge badge-warning mt-1">Minimal ball</span>
                                                @endif
                                            </td>
                                            <td class="align-middle text-center">
                                                <div class="d-flex justify-content-center">
                                                    <a href="{{ route('criteria.ratings.show', $value) }}"
                                                       class="btn btn-outline-info btn-sm mr-1"
                                                       title="Kriteriya reytingini ko‘rish">
                                                        <i class="fa fa-eye"></i>
                                                    </a>
                                                    @if($fourOneOneReplacementDatum?->criterion_id === $value->id)
                                                        <a href="{{ route('upload.four-one-one-reference.replace', $fourOneOneReplacementDatum) }}"
                                                           class="btn btn-outline-primary btn-sm"
                                                           title="Yangi resurs yuklash">
                                                            <i class="fa fa-plus"></i>
                                                        </a>
                                                    @elseif($resourceUploadsEnabled && ($value->upload == '1' || $value->isHIndexCriterion()) && $evaluation)
                                                        <a href="{{ route('upload.show', $value->id) }}"
                                                           class="btn btn-outline-primary btn-sm"
                                                           title="Ma’lumot kiritish">
                                                            <i class="fa fa-plus"></i>
                                                        </a>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-4 text-center text-muted">
                                                Ushbu bo‘limda faol mezonlar mavjud emas.
                                            </td>
                                        </tr>
                                    @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endsection
