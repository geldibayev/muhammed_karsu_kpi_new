@extends('layouts.app')

@section('content')
    <section class="content">
        @unless($resourceUploadsEnabled)
            <div class="alert alert-warning shadow-sm">
                <i class="fas fa-pause-circle mr-1" aria-hidden="true"></i>
                Tizimga resurs yuklash administrator tomonidan vaqtincha to‘xtatilgan.
            </div>
        @endunless

        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th class="text-center" style="width: 5%;">#</th>
                        <th>Mezon</th>
                        <th class="text-center" style="width: 20%;">Masʼul</th>
                        <th class="text-center" style="width: 10%;">Ball</th>
                        <th class="text-center" style="width: 10%;">Amallar</th>
                    </tr>
                    </thead>
                    <tbody>
                    @php($main = 1)
                    @foreach($criteria as $item)
                        <tr style="background-color: #eee">
                            <th class="align-middle text-center p-4">#{{ $main }}</th>
                            <th colspan="4" class="align-middle">
                                {{ data_get($item->name, 'uz', 'Nomsiz bo\'lim') }}
                            </th>
                        </tr>
                        @foreach($item->children as $value)
                            @php($evaluation = $value->criterionEvaluations->first())
                            <tr class="small">
                                <td class="align-middle text-center">{{ $main }}/{{ $value->id }}</td>
                                <td class="align-middle">
                                    <div class="font-weight-bold" style="text-align: justify">
                                        {{ data_get($value->name, 'uz', 'Nomsiz mezon') }}
                                    </div>
                                    <div style="text-align: justify">
                                        {!! data_get($value->desc, 'uz', '') !!}
                                    </div>
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
                                    <span class="font-weight-bold text-success">
                                        {{ number_format(auth()->user()->point($value->id), 2) }}
                                    </span>
                                    /
                                    <span class="font-weight-bold text-primary">
                                        {{ number_format($evaluation?->score ?? 0, 2) }}
                                    </span>
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
