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
                        <th class="text-center" style="width: 5%;">Ball</th>
                        <th class="text-center" style="width: 5%;"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @php($main = 1)
                    @foreach($criteria as $item)
                        <tr style="background-color: #eee">
                            <th class="align-middle text-center p-4">#{{ $main }}</th>
                            <th colspan="4" class="align-middle">
                                {{ $item->name['uz'] }}
                            </th>
                        </tr>
                        @foreach($item->children as $value)
                            <tr class="small">
                                <td class="align-middle text-center">{{ $main }}/{{ $value->id }}</td>
                                <td class="align-middle">
                                    <div class="font-weight-bold" style="text-align: justify">
                                        {{ $value->name['uz'] }}
                                    </div>
                                    <div style="text-align: justify">
                                        {!! $value->desc['uz'] !!}
                                    </div>
                                </td>
                                <td></td>
                                <td class="align-middle text-center">
                                    {{ $value->criterionEvaluation->score }}
                                </td>
                                <td class="align-middle text-center">
                                    <a href="{{ route('upload.show', $value->id) }}"
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fa fa-plus"></i>
                                    </a>
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
