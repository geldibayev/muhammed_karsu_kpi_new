@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="container-fluid">
            <div class="card card-outline card-primary">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold">Ma’sullar</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                            <tr>
                                <th style="width: 12%;">Mezon raqami</th>
                                <th>Mezon nomi</th>
                                <th style="width: 28%;">Ma’sul F.I.O.</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($criteria as $criterion)
                                @php
                                    $assignment = $criterion->reviewerAssignment;
                                    $reviewer = $assignment?->user;
                                    $criterionCode = $assignment?->criterion_code
                                        ?? (($parentNumbers[$criterion->parent_id] ?? '?').'/'.$criterion->id);
                                @endphp
                                <tr>
                                    <td class="align-middle font-weight-bold">{{ $criterionCode }}</td>
                                    <td class="align-middle">{{ data_get($criterion->name, 'uz', 'Nomsiz mezon') }}</td>
                                    <td class="align-middle">
                                        @if($reviewer)
                                            {{ $reviewer->full ?: $reviewer->short ?: 'Biriktirilmagan' }}
                                        @else
                                            <span class="text-muted">Biriktirilmagan</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted py-4">Mezonlar topilmadi.</td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
