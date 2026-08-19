@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title font-weight-bold">Foydalanuvchilar</h3>
                <div class="card-tools d-flex align-items-center">
                    @can('export-employment-data')
                        <a href="{{ route('users.external-part-timers.index') }}" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-user-tie mr-1"></i> Tashqi o‘rindoshlar
                        </a>
                    @endcan
                </div>
            </div>
            <div class="card-body border-bottom">
                <form method="GET" action="{{ route('users.roles.index') }}" class="form-row align-items-end">
                    <div class="col-md-6 col-lg-4">
                        <label for="user-search" class="small font-weight-bold">Foydalanuvchini izlash</label>
                        <input id="user-search" type="search" name="search" value="{{ $search }}"
                               class="form-control" placeholder="F.I.Sh. yoki HEMIS ID">
                    </div>
                    <div class="col-auto mt-2 mt-md-0">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search mr-1"></i> Izlash
                        </button>
                        @if($search !== '')
                            <a href="{{ route('users.roles.index') }}" class="btn btn-outline-secondary ml-1">
                                Tozalash
                            </a>
                        @endif
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Foydalanuvchi</th>
                            <th>Ish joyi</th>
                            <th>Holati</th>
                            <th class="text-right">Amal</th>
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($users as $user)
                            <tr>
                                <td class="align-middle">
                                    <div class="font-weight-bold">{{ $user->full ?: $user->short ?: 'Noma’lum foydalanuvchi' }}</div>
                                    <div class="small text-muted">HEMIS ID: {{ $user->hemis_id ?? '—' }}</div>
                                </td>
                                <td class="align-middle small">
                                    {{ $user->ratingWorkplace?->department?->name['uz'] ?? 'Biriktirilmagan' }}
                                </td>
                                <td class="align-middle">
                                    <span class="badge {{ $user->isActive() ? 'badge-success' : 'badge-secondary' }}">
                                        {{ $user->isActive() ? 'Faol' : 'Faol emas' }}
                                    </span>
                                </td>
                                <td class="align-middle text-right">
                                    @can('deactivate', $user)
                                        <form method="POST" action="{{ route('users.deactivation.update', $user) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit" class="btn btn-danger btn-sm"
                                                    onclick="return confirm('Foydalanuvchini faolsizlantirish, barcha resurs va ballarini reytingdan chiqarishni tasdiqlaysizmi?')">
                                                <i class="fas fa-user-slash mr-1"></i> Faolsizlantirish
                                            </button>
                                        </form>
                                    @else
                                        <span class="small text-muted">
                                            {{ $user->isActive() ? 'Himoyalangan' : 'Faolsizlantirilgan' }}
                                        </span>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">Foydalanuvchilar topilmadi.</td>
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
