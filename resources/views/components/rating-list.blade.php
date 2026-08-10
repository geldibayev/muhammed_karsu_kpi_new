@props([
    'departments',
    'faculties',
    'positions',
    'filters',
    'mode',
    'report',
    'unitRankings' => null,
    'users' => null,
    'filterRoute',
    'exportRoute' => null,
    'showActions' => false,
])

@php
    use App\Enums\RatingMode;

    $results = $unitRankings ?? $users;
    $isUnitOverview = $unitRankings !== null;
    $isAllUsersMode = $mode === RatingMode::AllUsers;
    $isFacultyMode = $mode === RatingMode::Faculties;
    $isDepartmentMode = $mode === RatingMode::Departments;
    $isDegreeMode = in_array($mode, [RatingMode::WithDegree, RatingMode::WithoutDegree], true);
    $selectedFaculty = $faculties->firstWhere('id', (int) ($filters['faculty'] ?? 0));
    $selectedDepartment = $departments->firstWhere('id', (int) ($filters['department'] ?? 0));
    $selectedPosition = $positions->firstWhere('id', (int) ($filters['position'] ?? 0));
    $exportFilters = array_filter(
        $filters,
        fn ($value, $key) => $key !== 'page' && $value !== null && $value !== '',
        ARRAY_FILTER_USE_BOTH,
    );
    $title = match (true) {
        $isAllUsersMode => 'Jami foydalanuvchilar',
        $isFacultyMode && $users !== null => data_get($selectedFaculty?->name, 'uz', 'Fakultet').' ichki reytingi',
        $isDepartmentMode && $users !== null => data_get($selectedDepartment?->name, 'uz', 'Kafedra').' ichki reytingi',
        $isFacultyMode => 'Fakultetlar reytingi',
        $isDepartmentMode => 'Kafedralar reytingi',
        $isDegreeMode && $selectedPosition => $selectedPosition->name.' — '.$mode->label().' reytingi',
        default => 'Foydalanuvchilar reytingi',
    };
    $totalLabel = match (true) {
        $isUnitOverview && $isFacultyMode => 'ta fakultet',
        $isUnitOverview && $isDepartmentMode => 'ta kafedra',
        default => 'nafar',
    };
@endphp

<section class="content">
    <div class="container-fluid">
        <div class="card card-outline card-primary">
            <div class="card-header px-4 py-3">
                <div class="d-flex flex-column flex-lg-row align-items-lg-center justify-content-between">
                    <div class="pr-lg-4">
                        <h1 class="h5 font-weight-bold mb-1">{{ $title }}</h1>
                        <p class="small text-muted mb-0">
                            @if($report)
                                {{ data_get($report->name, 'uz', 'Faol hisobot') }} bo‘yicha
                                {{ $isAllUsersMode ? 'resurs yuklash holati' : 'jami ballar' }}
                            @else
                                Faol hisobot topilmadi
                            @endif
                        </p>
                    </div>
                    <div class="d-flex align-items-center mt-3 mt-lg-0">
                        @if($exportRoute)
                            <a href="{{ route($exportRoute, $exportFilters) }}"
                               class="btn btn-success btn-sm mr-2">
                                <i class="fas fa-file-excel mr-1"></i> Excelga yuklash
                            </a>
                        @endif
                        <span class="badge badge-primary px-3 py-2">
                            Jami: {{ $results->total() }} {{ $totalLabel }}
                        </span>
                    </div>
                </div>
            </div>

            <div class="card-header p-0 border-bottom-0">
                <ul class="nav nav-tabs flex-column flex-md-row" role="tablist">
                    @foreach([
                        [RatingMode::AllUsers, 'fas fa-user-friends', 'Jami foydalanuvchilar'],
                        [RatingMode::WithDegree, 'fas fa-user-graduate', 'Ilmiy darajaga ega'],
                        [RatingMode::WithoutDegree, 'fas fa-users', 'Ilmiy darajaga ega emas'],
                        [RatingMode::Faculties, 'fas fa-university', 'Fakultet bo‘yicha'],
                        [RatingMode::Departments, 'fas fa-sitemap', 'Kafedra bo‘yicha'],
                    ] as [$tabMode, $icon, $label])
                        @php
                            $tabFilters = ['mode' => $tabMode->value];
                            if ($selectedPosition && in_array($tabMode, [RatingMode::WithDegree, RatingMode::WithoutDegree], true)) {
                                $tabFilters['position'] = $selectedPosition->id;
                            }
                        @endphp
                        <li class="nav-item">
                            <a href="{{ route($filterRoute, $tabFilters) }}"
                               class="nav-link @if($mode === $tabMode) active @endif">
                                <i class="{{ $icon }} mr-1"></i> {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>

            @if($isDegreeMode)
                <div class="card-header py-2">
                    <div class="small font-weight-bold text-muted mb-2">Lavozim bo‘yicha reyting</div>
                    <nav class="nav nav-pills flex-wrap" aria-label="Lavozim bo‘yicha reyting">
                        <a href="{{ route($filterRoute, array_filter([
                            ...$exportFilters,
                            'position' => null,
                        ])) }}"
                           class="nav-link py-1 px-3 mr-1 mb-1 @if(!$selectedPosition) active @endif"
                           @if(!$selectedPosition) aria-current="page" @endif>
                            Barchasi
                        </a>
                        @foreach($positions as $position)
                            <a href="{{ route($filterRoute, [
                                ...$exportFilters,
                                'position' => $position->id,
                            ]) }}"
                               class="nav-link py-1 px-3 mr-1 mb-1 @if($selectedPosition?->is($position)) active @endif"
                               @if($selectedPosition?->is($position)) aria-current="page" @endif>
                                {{ $position->name }}
                            </a>
                        @endforeach
                    </nav>
                </div>
            @endif

            <div class="card-body border-bottom">
                @if(($isFacultyMode || $isDepartmentMode) && $users !== null)
                    <div class="mb-3">
                        <a href="{{ route($filterRoute, array_filter([
                            'mode' => $mode->value,
                            'faculty' => $isDepartmentMode ? ($filters['faculty'] ?? null) : null,
                        ])) }}" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-arrow-left mr-1"></i>
                            {{ $isFacultyMode ? 'Barcha fakultetlar' : 'Barcha kafedralar' }}
                        </a>
                    </div>
                @endif

                <form method="GET" action="{{ route($filterRoute) }}">
                    <input type="hidden" name="mode" value="{{ $mode->value }}">
                    @if($selectedPosition)
                        <input type="hidden" name="position" value="{{ $selectedPosition->id }}">
                    @endif
                    <div class="row">
                        <div class="{{ $isAllUsersMode ? 'col-lg-3' : 'col-lg-4' }} col-md-6 mb-3 mb-lg-0">
                            <label class="small font-weight-bold" for="rating-search">
                                {{ $isUnitOverview
                                    ? ($isFacultyMode ? 'Fakultetni izlash' : 'Kafedrani izlash')
                                    : 'Foydalanuvchini izlash' }}
                            </label>
                            <div class="input-group">
                                <input id="rating-search" type="search" name="search" class="form-control"
                                       value="{{ $filters['search'] ?? '' }}"
                                       placeholder="{{ $isUnitOverview
                                            ? 'Tuzilma nomi bo‘yicha izlash'
                                            : 'F.I.Sh. bo‘yicha izlash' }}">
                                <div class="input-group-append">
                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                            <label class="small font-weight-bold" for="rating-faculty">Fakultet</label>
                            <select id="rating-faculty" name="faculty" class="form-control">
                                <option value="">Barcha fakultetlar</option>
                                @foreach($faculties as $faculty)
                                    <option value="{{ $faculty->id }}"
                                        @selected((int) ($filters['faculty'] ?? 0) === $faculty->id)>
                                        {{ data_get($faculty->name, 'uz', 'Nomsiz fakultet') }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        @if(!$isFacultyMode || !empty($filters['faculty']))
                            <div class="{{ $isAllUsersMode ? 'col-lg-2' : 'col-lg-3' }} col-md-6 mb-3 mb-lg-0">
                                <label class="small font-weight-bold" for="rating-department">Kafedra</label>
                                <select id="rating-department" name="department" class="form-control">
                                    <option value="">Barcha kafedralar</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department->id }}"
                                            @selected((int) ($filters['department'] ?? 0) === $department->id)>
                                            {{ data_get($department->name, 'uz', 'Nomsiz kafedra') }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        @if($isAllUsersMode)
                            <div class="col-lg-2 col-md-6 mb-3 mb-lg-0">
                                <label class="small font-weight-bold" for="rating-resource-status">Holati</label>
                                <select id="rating-resource-status" name="resource_status" class="form-control">
                                    <option value="">Barchasi</option>
                                    <option value="uploaded" @selected(($filters['resource_status'] ?? null) === 'uploaded')>
                                        Resurs yuklagan
                                    </option>
                                    <option value="not_uploaded" @selected(($filters['resource_status'] ?? null) === 'not_uploaded')>
                                        Resurs yuklamagan
                                    </option>
                                </select>
                            </div>
                        @endif
                        <div class="col-lg-2 col-md-6 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary mr-2">
                                <i class="fas fa-filter mr-1"></i> Ko‘rsatish
                            </button>
                            <a href="{{ route($filterRoute, ['mode' => $mode->value]) }}"
                               class="btn btn-outline-secondary" title="Filtrlarni tozalash">
                                <i class="fas fa-times"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    @if($isUnitOverview)
                        <table class="table table-hover small mb-0">
                            <thead>
                            <tr>
                                <th class="text-center">O‘rin</th>
                                @if($isDepartmentMode)<th>Fakultet</th>@endif
                                <th>{{ $isFacultyMode ? 'Fakultet' : 'Kafedra' }}</th>
                                <th class="text-center">Xodimlar</th>
                                <th class="text-center">Ilmiy darajali</th>
                                <th class="text-center">Darajasiz</th>
                                <th class="text-center">Jami ball</th>
                                <th class="text-center">O‘rtacha ball</th>
                                <th class="text-center">Amal</th>
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($unitRankings as $unit)
                                <tr>
                                    <td class="text-center align-middle font-weight-bold">
                                        {{ $unitRankings->firstItem() + $loop->index }}
                                    </td>
                                    @if($isDepartmentMode)
                                        <td class="align-middle">{{ $unit['faculty_name'] }}</td>
                                    @endif
                                    <td class="align-middle font-weight-bold">{{ $unit['name'] }}</td>
                                    <td class="text-center align-middle">{{ $unit['users_count'] }}</td>
                                    <td class="text-center align-middle">{{ $unit['with_degree_count'] }}</td>
                                    <td class="text-center align-middle">{{ $unit['without_degree_count'] }}</td>
                                    <td class="text-center align-middle">
                                        <span class="badge badge-success px-3 py-2">
                                            {{ number_format((float) $unit['total_points'], 2) }}
                                        </span>
                                    </td>
                                    <td class="text-center align-middle">
                                        {{ number_format((float) $unit['average_points'], 2) }}
                                    </td>
                                    <td class="text-center align-middle">
                                        <a href="{{ route($filterRoute, $isFacultyMode
                                            ? ['mode' => $mode->value, 'faculty' => $unit['id']]
                                            : [
                                                'mode' => $mode->value,
                                                'faculty' => $unit['faculty_id'],
                                                'department' => $unit['id'],
                                            ]) }}"
                                           class="btn btn-outline-primary btn-xs">
                                            <i class="fas fa-list-ol mr-1"></i> Ichki reyting
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isDepartmentMode ? 9 : 8 }}"
                                        class="text-center text-muted py-5">
                                        <i class="fas fa-university fa-2x d-block mb-2"></i>
                                        Tanlangan shartlar bo‘yicha tuzilmalar topilmadi.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    @else
                        <table class="table table-hover small mb-0">
                            <thead>
                            <tr>
                                <th class="text-center">{{ $isAllUsersMode ? 'T/r' : 'O‘rin' }}</th>
                                <th>Foydalanuvchi</th>
                                <th>Fakultet</th>
                                <th>Kafedra</th>
                                <th>Reytingdagi lavozimi</th>
                                @if($isAllUsersMode)
                                    <th class="text-center">Holati</th>
                                    <th class="text-center">Yuklagan resurslari</th>
                                    <th class="text-center">Tasdiqlangan</th>
                                    <th class="text-center">Qaytarilgan</th>
                                    <th class="text-center">Ko‘rib chiqilmoqda</th>
                                @else
                                    <th class="text-center">Jami ball</th>
                                @endif
                                @if($showActions)<th class="text-center">Amal</th>@endif
                            </tr>
                            </thead>
                            <tbody>
                            @forelse($users as $user)
                                @php
                                    $workplace = $user->ratingWorkplace;
                                    $department = $workplace?->department;
                                    $faculty = $department?->parent ?? ($department?->parent_id === null ? $department : null);
                                @endphp
                                <tr>
                                    <td class="text-center align-middle font-weight-bold">
                                        {{ $users->firstItem() + $loop->index }}
                                    </td>
                                    <td class="align-middle">
                                        <div class="d-flex align-items-center">
                                            @php($avatarUrl = $user->image_url)
                                            @if($avatarUrl)
                                                <img src="{{ $avatarUrl }}"
                                                     alt="{{ $user->full ?: 'Foydalanuvchi' }}"
                                                     class="img-circle elevation-1 img-size-50 mr-3 flex-shrink-0"
                                                     loading="lazy" data-rating-avatar-image>
                                            @endif
                                            <span data-rating-avatar-fallback
                                                  role="img"
                                                  aria-label="{{ $user->full ?: 'Foydalanuvchi' }}"
                                                  class="{{ $avatarUrl ? 'd-none' : 'd-inline-flex' }}
                                                      size-50 img-circle bg-light border text-secondary
                                                      align-items-center justify-content-center flex-shrink-0 mr-3">
                                                <i class="fas fa-user fa-lg" aria-hidden="true"></i>
                                            </span>
                                            <span class="font-weight-bold">
                                                {{ $user->full ?: ($user->short ?: 'Noma’lum foydalanuvchi') }}
                                            </span>
                                        </div>
                                    </td>
                                    <td class="align-middle">{{ data_get($faculty?->name, 'uz', '—') }}</td>
                                    <td class="align-middle">
                                        {{ $department?->parent_id !== null
                                            ? data_get($department->name, 'uz', '—')
                                            : '—' }}
                                    </td>
                                    <td class="align-middle">{{ $workplace?->position?->name ?? '—' }}</td>
                                    @if($isAllUsersMode)
                                        @php($resourceCount = (int) ($user->uploaded_resources_count ?? 0))
                                        <td class="text-center align-middle">
                                            <span class="badge {{ $resourceCount > 0 ? 'badge-success' : 'badge-secondary' }} px-3 py-2">
                                                {{ $resourceCount > 0 ? 'Resurs yuklagan' : 'Resurs yuklamagan' }}
                                            </span>
                                        </td>
                                        <td class="text-center align-middle font-weight-bold">
                                            {{ $resourceCount }}
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="badge badge-success px-3 py-2">
                                                {{ (int) ($user->accepted_resources_count ?? 0) }}
                                            </span>
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="badge badge-danger px-3 py-2">
                                                {{ (int) ($user->cancelled_resources_count ?? 0) }}
                                            </span>
                                        </td>
                                        <td class="text-center align-middle">
                                            <span class="badge badge-warning px-3 py-2"
                                                  title="Yuborilgan va tekshirilayotgan resurslar">
                                                {{ (int) ($user->reviewing_resources_count ?? 0) }}
                                            </span>
                                        </td>
                                    @else
                                        <td class="text-center align-middle">
                                            <span class="badge badge-success px-3 py-2">
                                                {{ number_format((float) ($user->total_points ?? 0), 2) }}
                                            </span>
                                        </td>
                                    @endif
                                    @if($showActions)
                                        <td class="text-center align-middle">
                                            <a href="{{ route('ratings.show', array_merge(['user' => $user], $exportFilters)) }}"
                                               class="btn btn-outline-primary btn-xs">
                                                <i class="fas fa-eye mr-1"></i> Ko‘rish
                                            </a>
                                        </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ ($isAllUsersMode ? 10 : 6) + ($showActions ? 1 : 0) }}" class="text-center text-muted py-5">
                                        <i class="fas fa-users fa-2x d-block mb-2"></i>
                                        Tanlangan shartlar bo‘yicha foydalanuvchilar topilmadi.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    @endif
                </div>
            </div>

            @if($results->hasPages())
                <div class="card-footer clearfix">
                    {{ $results->onEachSide(1)->links() }}
                </div>
            @endif
        </div>
    </div>
</section>
