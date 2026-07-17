@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="row">
            <div class="col-md-6">
                <div class="card card-default">
                    <div class="card-header font-weight-bold">
                        Mening profil ma’lumotlarim
                    </div>
                    <div class="card-body p-0 small">
                        <table class="table">
                            <tbody>
                            <tr>
                                <th class="align-middle" style="width: 30%">Rasm</th>
                                @php $image = json_decode($user->image)->max; @endphp
                                <td>
                                    <img src="{{ $image }}" alt="" style="width: 100px">
                                </td>
                            </tr>
                            <tr>
                                <th class="align-middle">ID raqami</th>
                                <td>{{ $user->hemis_id }}</td>
                            </tr>
                            <tr>
                                <th class="align-middle">Ismi</th>
                                <td>{{ $user->first }}</td>
                            </tr>
                            <tr>
                                <th class="align-middle">Familiya</th>
                                <td>{{ $user->last }}</td>
                            </tr>
                            <tr>
                                <th class="align-middle">Otasining ismi</th>
                                <td>{{ $user->third }}</td>
                            </tr>
                            <tr>
                                <th class="align-middle">Ilmiy unvon</th>
                                <td>{{ $workpl->academic_rank->name }}</td>
                            </tr>
                            <tr>
                                <th class="align-middle">Ilmiy daraja</th>
                                <td>{{ $workpl->academic_degree->name }}</td>
                            </tr>
                            <tr>
                                <th class="align-middle">Yaratilgan</th>
                                <td>{{ $user->created_at->format('d.m.Y H:i:s') }}</td>
                            </tr>
                            <tr>
                                <th class="align-middle">O‘zgartirilgan</th>
                                <td>{{ $user->updated_at->format('d.m.Y H:i:s') }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header font-weight-bold">
                        Ish joyi va lavozimi ma’lumotlari
                    </div>
                    <div class="card-body small p-0">
                        <table class="table ">
                            <thead>
                            <tr>
                                <th class="align-middle">#</th>
                                <th class="align-middle">Kafedra / Bo‘lim</th>
                                <th class="align-middle">Stavka</th>
                                <th class="align-middle">Mehnat shakli</th>
                                <th class="align-middle">Lavozim</th>
                                <th class="align-middle">Holati</th>
                            </tr>
                            </thead>
                            <tbody>
                            @php($i = 1)
                            @foreach($user->workplaces as $workplace)
                                <tr>
                                    <td>{{ $i++ }}</td>
                                    <td>{{ $workplace->department->name['uz'] }}</td>
                                    <td>{{ $workplace->staff->name }}</td>
                                    <td>{{ $workplace->form->name }}</td>
                                    <td>{{ $workplace->position->name }}</td>
                                    <td>{{ $workplace->status->name }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
