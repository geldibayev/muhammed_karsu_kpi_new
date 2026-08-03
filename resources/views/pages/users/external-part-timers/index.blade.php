@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="card card-outline card-primary">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div>
                    <h3 class="card-title font-weight-bold">Tashqi o‘rindoshlar</h3>
                    <div class="small text-muted mt-1">Jami: {{ $users->total() }} nafar</div>
                </div>
                <a href="{{ route('users.external-part-timers.export') }}" class="btn btn-success btn-sm ml-auto">
                    <i class="fas fa-file-excel mr-1"></i> Excelga yuklash
                </a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead>
                        <tr>
                            <th style="width: 60px">T/r</th>
                            <th>F.I.Sh.</th>
                            <th>HEMIS ID</th>
                            <th>Fakultet</th>
                            <th>Kafedra</th>
                            <th>Lavozim</th>
                            <th>Mehnat shakli</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td>{{ ($users->firstItem() ?? 1) + $loop->index }}</td>
                                <td class="font-weight-bold">{{ $user['name'] }}</td>
                                <td>{{ $user['hemis_id'] }}</td>
                                <td>{{ $user['faculties'] }}</td>
                                <td>{{ $user['departments'] }}</td>
                                <td>{{ $user['positions'] }}</td>
                                <td>{{ $user['forms'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    Tashqi o‘rindosh foydalanuvchilar topilmadi.
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            @if($users->hasPages())
                <div class="card-footer">{{ $users->links() }}</div>
            @endif
        </div>
    </section>
@endsection
