@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="container-fluid">
            @php
                $workplace = $user->ratingWorkplace;
                $department = $workplace?->department;
                $faculty = $department?->parent ?? ($department?->parent_id === null ? $department : null);
            @endphp

            <div class="card card-outline card-primary shadow-sm">
                <div class="card-body">
                    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between">
                        <div class="d-flex align-items-center">
                            <img src="{{ $user->image_url ?: asset('dist/img/default-150x150.png') }}"
                                 alt="{{ $user->full ?: 'Foydalanuvchi' }}"
                                 class="img-circle elevation-1 img-size-64 mr-3">
                            <div>
                                <h1 class="h5 font-weight-bold mb-1">
                                    {{ $user->full ?: ($user->short ?: 'Noma’lum foydalanuvchi') }}
                                </h1>
                                <div class="small text-muted">
                                    {{ data_get($faculty?->name, 'uz', 'Fakultet biriktirilmagan') }}
                                    @if($department?->parent_id !== null)
                                        / {{ data_get($department->name, 'uz', 'Kafedra biriktirilmagan') }}
                                    @endif
                                </div>
                                <div class="small text-muted">
                                    {{ $workplace?->position?->name ?? 'Lavozim biriktirilmagan' }}
                                </div>
                            </div>
                        </div>
                        <a href="{{ route('ratings.index', $filters) }}"
                           class="btn btn-outline-secondary mt-3 mt-md-0">
                            <i class="fas fa-arrow-left mr-1" aria-hidden="true"></i>
                            Reytingga qaytish
                        </a>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <div>
                        <h2 class="h6 font-weight-bold mb-1">Kriteriyalar bo‘yicha ballar</h2>
                        <div class="small text-muted">
                            {{ $report ? data_get($report->name, 'uz', 'Faol hisobot') : 'Faol hisobot topilmadi' }}
                        </div>
                    </div>
                    <span class="badge badge-success px-3 py-2 mt-2 mt-sm-0">
                        Jami: {{ number_format($totalPoints, 2) }} ball
                    </span>
                </div>

                <div class="card-body p-0">
                    @forelse($criterionSections as $section)
                        <section @class(['border-bottom' => ! $loop->last])>
                            <div class="bg-light border-bottom px-3 py-3 d-flex align-items-center">
                                <span class="badge badge-primary px-2 py-1 mr-2">#{{ $section['number'] }}</span>
                                <h3 class="h6 font-weight-bold mb-0">
                                    {{ data_get($section['criterion']->name, 'uz', 'Nomsiz bo‘lim') }}
                                </h3>
                            </div>

                            @foreach($section['rows'] as $score)
                                @php
                                    $acceptedCollapseId = 'accepted-submissions-'.$score['criterion']->getKey();
                                    $cancelledCollapseId = 'cancelled-submissions-'.$score['criterion']->getKey();
                                    $pendingCollapseId = 'pending-submissions-'.$score['criterion']->getKey();
                                @endphp
                                <article data-testid="rating-criterion-row"
                                         @class(['px-3 py-3', 'border-bottom' => ! $loop->last])>
                                    <div class="row align-items-start">
                                        <div class="col-lg-3 col-md-12 mb-3 mb-lg-0">
                                            <div class="d-flex align-items-start">
                                                <span class="badge badge-light border text-primary text-nowrap mr-2 mt-1">
                                                    {{ $score['code'] }}
                                                </span>
                                                <div class="font-weight-bold">
                                                    {{ data_get($score['criterion']->name, 'uz', 'Nomsiz kriteriya') }}
                                                </div>
                                            </div>
                                        </div>

                                        <div class="col-lg-2 col-md-4 mb-3 mb-lg-0">
                                            <div class="small text-muted text-uppercase font-weight-bold mb-2">Natija</div>
                                            @if($score['state'] === 'scored')
                                                <span class="badge badge-success px-3 py-2">
                                                    {{ number_format($score['point'], 2) }} ball
                                                </span>
                                            @elseif($score['state'] === 'pending')
                                                <span class="badge badge-warning px-3 py-2">Baholanmagan</span>
                                            @elseif($score['state'] === 'accepted')
                                                <span class="badge badge-info px-3 py-2">Tasdiqlangan</span>
                                            @elseif($score['state'] === 'cancelled')
                                                <span class="badge badge-danger px-3 py-2">Qaytarilgan</span>
                                            @else
                                                <span class="badge badge-secondary px-3 py-2">Yuklanmagan</span>
                                            @endif

                                            @if($score['pending_count'] > 0)
                                                <div class="mt-2">
                                                    <span class="badge badge-warning">
                                                        {{ $score['pending_count'] }} ta baholanmagan yuklama
                                                    </span>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="col-lg-2 col-md-4 mb-3 mb-lg-0">
                                            <div class="small text-muted text-uppercase font-weight-bold mb-2">
                                                Baholash usuli
                                            </div>
                                            <x-rating-method
                                                :method="$score['rating_method']"
                                                :criterion="$score['criterion']"
                                            />
                                        </div>

                                        <div class="col-lg-2 col-md-8 mb-3 mb-lg-0">
                                            <div class="small text-muted text-uppercase font-weight-bold mb-2">
                                                Resurslar
                                            </div>
                                            @if($score['pending_submissions']->isNotEmpty())
                                                <button type="button"
                                                        class="btn btn-outline-warning btn-sm text-left"
                                                        data-toggle="collapse"
                                                        data-target="#{{ $pendingCollapseId }}"
                                                        aria-controls="{{ $pendingCollapseId }}"
                                                        aria-expanded="false">
                                                    <i class="fas fa-clock mr-1" aria-hidden="true"></i>
                                                    {{ $score['pending_submissions']->count() }} ta baholanmagan
                                                    <i class="fas fa-chevron-down ml-1" aria-hidden="true"></i>
                                                </button>
                                            @endif

                                            @if($score['accepted_submissions']->isNotEmpty())
                                                <button type="button"
                                                        class="btn btn-outline-success btn-sm text-left mt-1"
                                                        data-toggle="collapse"
                                                        data-target="#{{ $acceptedCollapseId }}"
                                                        aria-controls="{{ $acceptedCollapseId }}"
                                                        aria-expanded="false">
                                                    <i class="fas fa-folder-open mr-1" aria-hidden="true"></i>
                                                    {{ $score['accepted_submissions']->count() }} ta tasdiqlangan
                                                    <i class="fas fa-chevron-down ml-1" aria-hidden="true"></i>
                                                </button>
                                            @elseif($score['cancelled_submissions']->isEmpty() && $score['pending_submissions']->isEmpty())
                                                <span class="text-muted small">
                                                    <i class="far fa-folder-open mr-1" aria-hidden="true"></i>
                                                    Resurs yo‘q
                                                </span>
                                            @endif

                                            @if($score['cancelled_submissions']->isNotEmpty())
                                                <button type="button"
                                                        class="btn btn-outline-danger btn-sm text-left mt-1"
                                                        data-toggle="collapse"
                                                        data-target="#{{ $cancelledCollapseId }}"
                                                        aria-controls="{{ $cancelledCollapseId }}"
                                                        aria-expanded="false">
                                                    <i class="fas fa-undo-alt mr-1" aria-hidden="true"></i>
                                                    {{ $score['cancelled_submissions']->count() }} ta qaytarilgan
                                                    <i class="fas fa-chevron-down ml-1" aria-hidden="true"></i>
                                                </button>
                                            @endif
                                        </div>

                                        <div class="col-lg-3 col-md-12">
                                            <div class="small text-muted text-uppercase font-weight-bold mb-2">Baholagan</div>
                                            <div class="d-flex flex-wrap align-items-start">
                                                @foreach($score['evaluators'] as $evaluator)
                                                    <span class="badge {{ $evaluator['type'] === 'manual' ? 'badge-primary' : ($evaluator['type'] === 'ai' ? 'badge-info' : ($evaluator['type'] === 'pending' ? 'badge-warning' : 'badge-secondary')) }} mr-1 mb-1 px-2 py-1">
                                                        @if($evaluator['type'] === 'ai')
                                                            <i class="fas fa-robot mr-1" aria-hidden="true"></i>
                                                        @elseif($evaluator['type'] === 'pending')
                                                            <i class="fas fa-clock mr-1" aria-hidden="true"></i>
                                                        @elseif($evaluator['type'] === 'unuploaded')
                                                            <i class="fas fa-upload mr-1" aria-hidden="true"></i>
                                                        @else
                                                            <i class="fas fa-user-check mr-1" aria-hidden="true"></i>
                                                        @endif
                                                        {{ $evaluator['name'] }}
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>

                                    @if($score['pending_submissions']->isNotEmpty())
                                        <div id="{{ $pendingCollapseId }}" class="collapse mt-3">
                                            <div class="list-group list-group-flush border border-warning rounded">
                                                @foreach($score['pending_submissions'] as $submission)
                                                    <a href="{{ route('upload.details', $submission) }}"
                                                       class="list-group-item list-group-item-warning list-group-item-action d-flex align-items-center justify-content-between py-2">
                                                        <span class="text-dark text-nowrap">
                                                            <i class="fas fa-file-alt mr-2" aria-hidden="true"></i>
                                                            Resurs #{{ $submission->id }}
                                                        </span>
                                                        <span class="badge badge-warning px-2 py-1 ml-3 text-nowrap">
                                                            {{ \App\Enums\DatumStatus::from($submission->status)->label() }}
                                                        </span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if($score['accepted_submissions']->isNotEmpty())
                                        <div id="{{ $acceptedCollapseId }}" class="collapse mt-3">
                                            <div class="list-group list-group-flush border rounded">
                                                @foreach($score['accepted_submissions'] as $submission)
                                                    <a href="{{ route('upload.details', $submission) }}"
                                                       class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2">
                                                        <span class="text-primary text-nowrap">
                                                            <i class="fas fa-file-alt mr-2" aria-hidden="true"></i>
                                                            Resurs #{{ $submission->id }}
                                                            @if($submission->isFinalReviewConfirmed())
                                                                <i class="fas fa-star text-warning ml-1"
                                                                   title="Yakuniy tekshiruv tasdiqlangan"
                                                                   aria-label="Yakuniy tekshiruv tasdiqlangan"></i>
                                                            @endif
                                                        </span>
                                                        <span class="badge badge-success px-2 py-1 ml-3 text-nowrap">
                                                            {{ number_format($submission->point, 2) }} ball
                                                        </span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    @if($score['cancelled_submissions']->isNotEmpty())
                                        <div id="{{ $cancelledCollapseId }}" class="collapse mt-3">
                                            <div class="list-group list-group-flush border border-danger rounded">
                                                @foreach($score['cancelled_submissions'] as $submission)
                                                    <a href="{{ route('upload.details', $submission) }}"
                                                       class="list-group-item list-group-item-danger list-group-item-action d-flex align-items-center justify-content-between py-2">
                                                        <span class="text-danger text-nowrap">
                                                            <i class="fas fa-file-alt mr-2" aria-hidden="true"></i>
                                                            Resurs #{{ $submission->id }}
                                                        </span>
                                                        <span class="badge badge-danger px-2 py-1 ml-3 text-nowrap">
                                                            Qaytarilgan
                                                        </span>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif
                                </article>
                            @endforeach
                        </section>
                    @empty
                        <div class="text-center text-muted py-5">
                            <i class="far fa-folder-open d-block mb-2" aria-hidden="true"></i>
                            Faol hisobot uchun kriteriyalar mavjud emas.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </section>
@endsection
