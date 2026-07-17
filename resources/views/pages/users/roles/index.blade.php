@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="card card-outline card-primary">
            <div class="card-header">
                <h3 class="card-title font-weight-bold">Foydalanuvchi rollari</h3>
                <div class="card-tools text-muted small">Rollarni faqat super administrator o‘zgartira oladi.</div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>Foydalanuvchi</th>
                            <th>Ish joyi</th>
                            <th>Rollar</th>
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
                                    {{ $user->workplaces->first()?->department?->name['uz'] ?? 'Biriktirilmagan' }}
                                </td>
                                <td class="align-middle">
                                    @if($user->isSuperAdmin())
                                        <span class="badge badge-danger">Super admin</span>
                                    @else
                                        <form id="role-form-{{ $user->id }}" method="POST" action="{{ route('users.roles.update', $user) }}" class="d-flex flex-wrap align-items-center">
                                            @csrf
                                            @method('PUT')
                                            @foreach($roles as $key => $label)
                                                <div class="custom-control custom-checkbox custom-control-inline mr-3 mb-1">
                                                    <input class="custom-control-input" type="checkbox" name="roles[]"
                                                           value="{{ $key }}" id="role-{{ $user->id }}-{{ $key }}"
                                                           @checked($user->hasRole($key))>
                                                    <label class="custom-control-label" for="role-{{ $user->id }}-{{ $key }}">{{ $label }}</label>
                                                </div>
                                            @endforeach
                                        </form>
                                    @endif
                                </td>
                                <td class="align-middle text-right">
                                    @unless($user->isSuperAdmin())
                                        <button type="submit" form="role-form-{{ $user->id }}" class="btn btn-primary btn-sm">
                                            <i class="fas fa-save mr-1"></i> Saqlash
                                        </button>
                                    @else
                                        <span class="small text-muted">Himoyalangan rol</span>
                                    @endunless
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
