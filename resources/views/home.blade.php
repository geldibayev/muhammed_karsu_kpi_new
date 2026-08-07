@extends('layouts.app')

@section('content')
    <section class="content">
        @unless($resourceUploadsEnabled)
            <div class="alert alert-warning shadow-sm">
                <i class="fas fa-pause-circle mr-1" aria-hidden="true"></i>
                Tizimga resurs yuklash administrator tomonidan vaqtincha to‘xtatilgan.
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
                        ko‘proq ball to‘plashga intiling.
                    </p>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover">
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
                    @php($main = 1)
                    @foreach($criteria as $item)
                        <tr style="background-color: #eee">
                            <th class="align-middle text-center p-4">#{{ $main }}</th>
                            <th colspan="5" class="align-middle">
                                {{ data_get($item->name, 'uz', 'Nomsiz bo\'lim') }}
                            </th>
                        </tr>
                        @foreach($item->children as $value)
                            @php($evaluation = $value->criterionEvaluations->first())
                            @php($isPrimaryIndicator = $value->isPrimaryIndicator())
                            <tr @class(['small', 'table-warning' => $isPrimaryIndicator])
                                @if($isPrimaryIndicator) data-testid="primary-indicator-row" @endif>
                                <td class="align-middle text-center">{{ $value->code ?: $main.'/'.$value->id }}</td>
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
                                        @if($resourceUploadsEnabled && ($value->upload == '1' || $value->isHIndexCriterion()) && $evaluation)
                                            <a href="{{ route('upload.show', $value->id) }}"
                                               class="btn btn-outline-primary btn-sm"
                                               title="Ma’lumot kiritish">
                                                <i class="fa fa-plus"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        @php($main++)
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
