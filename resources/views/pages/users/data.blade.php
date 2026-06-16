@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="card">
            <div class="card-body p-0">
                <table class="table table-hover small">
                    <thead>
                    <tr>
                        <th class="text-center" style="width: 5%;">#</th>
                        <th>Resurs ma’lumotlari</th>
                        <th class="text-center" style="width: 5%;">Holati</th>
                        @if ($status != 'received' && $status != 'checking')
                            <th class="text-center">Tekshiruvchi xulosasi</th>
                            <th class="text-center" style="width: 5%;">Ball</th>
                        @endif
                        <th class="text-center" style="width: 10%;">Yuborilgan vaqti</th>
                        <th class="text-center" style="width: 7%;"></th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach($data as $datum)
                        <tr class="text-center">
                            <td>#{{ $datum->id }}</td>
                            <td class="text-left">
                                @if($datum->material['type'] == 'url')
                                    <a href="{{ $datum->material['link'] }}">{{ $datum->name }}</a>
                                @else
                                    {{ $datum->name }}
                                @endif
                            </td>
                            <td>
                                @if($datum->status == 'received')
                                    <div class="badge badge-primary">Yangi resurs</div>
                                @elseif($datum->status == 'checking')
                                    <div class="badge badge-warning">Tekshirilmoqda</div>
                                @elseif($datum->status == 'accepted')
                                    <div class="badge badge-success">Qabul qilingan</div>
                                @elseif($datum->status == 'cancelled')
                                    <div class="badge badge-dark">Bekor qilingan</div>
                                @endif
                            </td>
                            @if ($status != 'received' && $status != 'checking')
                                <td>
                                    {!! $datum->reason ?? '' !!}
                                </td>
                                <td>
                                    {{ number_format($datum->point ?? 0, 2) }}
                                </td>
                            @endif
                            <td>{{ $datum->created_at->format('d.m.Y H:i:s') }}</td>
                            <td>
                                <a href="#" class="btn btn-outline-primary btn-xs">
                                    <i class="fa fa-eye"></i>
                                    Ko‘rish
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </section>
@endsection
