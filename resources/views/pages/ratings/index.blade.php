@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header px-4 py-3">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                        <div class="pr-md-4">
                            <h3 class="h5 font-weight-bold mb-1">Foydalanuvchilar reytingi</h3>
                            <p class="small text-muted mb-0">
                                @if($report)
                                    {{ data_get($report->name, 'uz', 'Faol hisobot') }} bo‘yicha jami ballar
                                @else
                                    Faol hisobot topilmadi
                                @endif
                            </p>
                        </div>
                        <span class="badge badge-primary px-3 py-2 mt-3 mt-md-0 align-self-start align-self-md-center">
                            Jami: {{ $users->total() }}
                        </span>
                    </div>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover small mb-0">
                            <thead>
                            <tr>
                                <th class="text-center" style="width: 8%;">O‘rin</th>
                                <th>Foydalanuvchi</th>
                                <th class="text-center" style="width: 20%;">Lavozimi</th>
                                <th class="text-center" style="width: 16%;">Jami ball</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($users as $user)
                                <tr>
                                    <td class="text-center align-middle font-weight-bold">
                                        {{ $users->firstItem() + $loop->index }}
                                    </td>
                                    <td class="align-middle font-weight-bold">
                                        {{ $user->full ?: ($user->short ?: 'Noma’lum foydalanuvchi') }}
                                    </td>
                                    <td class="text-center align-middle text-muted">
                                        {{ $user->pos ?: '—' }}
                                    </td>
                                    <td class="text-center align-middle">
                                        <span class="badge badge-success px-3 py-2">
                                            {{ number_format((float) ($user->total_points ?? 0), 2) }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-5">
                                        <i class="fas fa-users fa-2x d-block mb-2"></i>
                                        Foydalanuvchilar topilmadi.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if($users->hasPages())
                    <div class="card-footer clearfix">
                        {{ $users->onEachSide(1)->links() }}
                    </div>
                @endif
            </div>
        </div>
    </section>
@endsection
