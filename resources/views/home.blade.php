@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th class="text-center" style="width: 5%;">#</th>
                        <th>Mezon</th>
                        <th class="text-center" style="width: 20%;">Masʼul</th>
                        <th class="text-center" style="width: 10%;">Ball</th>
                        <th class="text-center" style="width: 5%;"></th>
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
                                        <i class="fa fa-robot"></i>
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
                                    @if($value->upload == '1')
                                        <a href="{{ route('upload.show', $value->id) }}"
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fa fa-plus"></i>
                                        </a>
                                    @endif
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
