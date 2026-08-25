@extends('layouts.app')

@section('css')
    <link rel="stylesheet" href="{{ asset('plugins/select2/css/select2.min.css') }}">
    <link rel="stylesheet" href="{{ asset('plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css') }}">
@endsection

@section('content')
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card card-outline {{ $resourceUploadsAvailable ? 'card-success' : 'card-warning' }} shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-upload mr-1" aria-hidden="true"></i>
                                Resurs yuklash
                            </h3>
                        </div>
                        <form method="POST" action="{{ route('settings.uploads.update') }}">
                            @csrf
                            @method('PUT')
                            <div class="card-body">
                                <div class="alert {{ $resourceUploadsAvailable ? 'alert-success' : 'alert-warning' }}">
                                    <div class="font-weight-bold">
                                        Joriy holat:
                                        @if(! $resourceUploadWindowOpen)
                                            Yuklash muddati yakunlangan
                                        @elseif($resourceUploadsEnabled)
                                            Yuklashga ruxsat berilgan
                                        @else
                                            Yuklash vaqtincha o‘chirilgan
                                        @endif
                                    </div>
                                    <div class="small mt-1">
                                        Oxirgi muddat: {{ $resourceUploadDeadlineLabel }}.
                                        @if($resourceUploadWindowOpen)
                                            O‘chirilganda foydalanuvchilar fayl, URL va H-index ma’lumotlarini tizimga yubora olmaydi.
                                        @else
                                            Global sozlama yoqilgan bo‘lsa ham yangi resurslar qabul qilinmaydi.
                                            Bir martalik maxsus ruxsatlar bundan mustasno.
                                        @endif
                                        Avval yuklangan resurslar saqlanib qoladi.
                                    </div>
                                </div>

                                <div class="form-group mb-0">
                                    <label for="resource_uploads_enabled">Yuklash holati</label>
                                    <select id="resource_uploads_enabled" name="resource_uploads_enabled"
                                            class="form-control @error('resource_uploads_enabled') is-invalid @enderror"
                                            required>
                                        <option value="1" @selected((string) old('resource_uploads_enabled', (int) $resourceUploadsEnabled) === '1')>
                                            Yuklashga ruxsat berish
                                        </option>
                                        <option value="0" @selected((string) old('resource_uploads_enabled', (int) $resourceUploadsEnabled) === '0')>
                                            Yuklashni vaqtincha o‘chirish
                                        </option>
                                    </select>
                                    @error('resource_uploads_enabled')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1" aria-hidden="true"></i>
                                    Saqlash
                                </button>
                            </div>
                        </form>
                    </div>

                    @can('manage-upload-permissions')
                    <div class="card card-outline card-info shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-user-check mr-1" aria-hidden="true"></i>
                                Maxsus yuklash ruxsati
                            </h3>
                        </div>
                        <form method="POST" action="{{ route('settings.upload-permissions.store') }}">
                            @csrf
                            <div class="card-body">
                                <div class="alert alert-info small">
                                    Bu ruxsat umumiy yuklash yopiq yoki muddati tugagan bo‘lsa ham tanlangan foydalanuvchi va
                                    kriteriyalar uchun ishlaydi. Foydalanuvchi har bir kriteriyaning mavjud resurs limiti
                                    doirasida qolgan resurslarini yuklay oladi.
                                </div>
                                <div class="form-row">
                                    <div class="form-group col-md-4">
                                        <label for="permission-user-id">Foydalanuvchi</label>
                                        <select id="permission-user-id" name="user_id" required
                                                class="form-control select2bs4 @error('user_id') is-invalid @enderror">
                                            <option value="">Foydalanuvchini izlang</option>
                                            @foreach($uploadPermissionUsers as $user)
                                                <option value="{{ $user->id }}" @selected(old('user_id') == $user->id)>
                                                    {{ $user->full ?: $user->short }} — HEMIS ID: {{ $user->hemis_id }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('user_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                    <div class="form-group col-md-8">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <label for="permission-criterion-ids">Kriteriyalar</label>
                                            <div class="custom-control custom-checkbox mb-2">
                                                <input id="permission-all-criteria" name="all_criteria" type="checkbox" value="1"
                                                       class="custom-control-input" @checked(old('all_criteria'))>
                                                <label class="custom-control-label font-weight-normal" for="permission-all-criteria">
                                                    Barcha mos kriteriyalar
                                                </label>
                                            </div>
                                        </div>
                                        <select id="permission-criterion-ids" name="criterion_ids[]" multiple
                                                class="form-control {{ $errors->has('criterion_ids') || $errors->has('criterion_ids.*') ? 'is-invalid' : '' }}">
                                            @foreach($uploadPermissionCriteria as $criterion)
                                                <option value="{{ $criterion->id }}" @selected(in_array($criterion->id, old('criterion_ids', [])))>
                                                    @if(filled($criterion->code)){{ $criterion->code }} — @endif
                                                    {{ data_get($criterion->parent?->name, 'uz', 'Bo‘limsiz') }} /
                                                    {{ data_get($criterion->name, 'uz', 'Nomsiz kriteriya') }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('criterion_ids')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @else
                                            @if($errors->has('criterion_ids.*'))
                                                <div class="invalid-feedback">{{ $errors->first('criterion_ids.*') }}</div>
                                            @endif
                                        @enderror
                                    </div>
                                </div>
                                <div class="form-group mb-0">
                                    <label for="permission-reason">Ruxsat sababi</label>
                                    <textarea id="permission-reason" name="reason" rows="3" maxlength="5000" required
                                              class="form-control @error('reason') is-invalid @enderror"
                                              placeholder="Masalan: maqola yuklash muddati tugagandan keyin Scopus bazasida indeksatsiya qilindi">{{ old('reason') }}</textarea>
                                    @error('reason')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-info">
                                    <i class="fas fa-key mr-1" aria-hidden="true"></i>
                                    Ruxsat berish
                                </button>
                            </div>
                        </form>

                        @if($uploadPermissions->isNotEmpty())
                            <div class="table-responsive border-top">
                                <table class="table table-sm table-hover mb-0">
                                    <thead>
                                    <tr>
                                        <th>Foydalanuvchi</th>
                                        <th>Kriteriya</th>
                                        <th>Ruxsat bergan</th>
                                        <th class="text-right">Amal</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    @foreach($uploadPermissions as $permission)
                                        <tr>
                                            <td>
                                                {{ $permission->user?->full ?: $permission->user?->short }}
                                                <div class="small text-muted">HEMIS ID: {{ $permission->user?->hemis_id }}</div>
                                                <div class="small text-muted">Sabab: {{ $permission->reason }}</div>
                                            </td>
                                            <td>
                                                @if(filled($permission->criterion?->code)){{ $permission->criterion->code }} — @endif
                                                {{ data_get($permission->criterion?->name, 'uz', 'Nomsiz kriteriya') }}
                                            </td>
                                            <td>{{ $permission->grantedBy?->full ?: $permission->grantedBy?->short ?: '—' }}</td>
                                            <td class="text-right">
                                                <form method="POST"
                                                      action="{{ route('settings.upload-permissions.destroy', $permission) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-outline-danger btn-sm"
                                                            onclick="return confirm('Maxsus yuklash ruxsatini bekor qilasizmi?')">
                                                        Bekor qilish
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                    @endcan

                    <div class="card card-outline {{ $aiEvaluationsEnabled && ! $aiQueuePaused ? 'card-success' : 'card-danger' }} shadow-sm">
                        <div class="card-header">
                            <h3 class="card-title font-weight-bold">
                                <i class="fas fa-robot mr-1" aria-hidden="true"></i>
                                AI tekshiruvi
                            </h3>
                        </div>
                        <form method="POST" action="{{ route('settings.ai.update') }}">
                            @csrf
                            @method('PUT')
                            <div class="card-body">
                                <div class="alert {{ $aiEvaluationsEnabled && ! $aiQueuePaused ? 'alert-success' : 'alert-danger' }}">
                                    <div class="font-weight-bold">
                                        Global sozlama:
                                        {{ $aiEvaluationsEnabled ? 'AI tekshiruvi yoqilgan' : 'AI tekshiruvi o‘chirilgan' }}
                                    </div>
                                    <div class="font-weight-bold mt-1">
                                        AI navbati:
                                        {{ $aiQueuePaused ? 'Pauzada' : 'Ishlashga tayyor' }}
                                        @if ($aiQueuePaused && $aiQueuePausedBySetting)
                                            (ushbu sozlama orqali)
                                        @elseif ($aiQueuePaused)
                                            (tizim yoki Gemini krediti sabab)
                                        @endif
                                    </div>
                                    @if ($aiQueuePaused && ! $aiQueuePausedBySetting && is_string($aiQueuePausedReason))
                                        <div class="small mt-1">Sabab: {{ $aiQueuePausedReason }}</div>
                                    @endif
                                    <div class="small mt-1">
                                        O‘chirilganda Gemini’ga yangi so‘rov yuborilmaydi. Navbatdagi resurslar o‘chirilmaydi;
                                        AI qayta yoqilganda worker ularni davom ettiradi. Hozir ishlanayotgan bitta so‘rov yakunlanishi mumkin.
                                    </div>
                                    @if ($aiEvaluationsEnabled && $aiQueuePaused)
                                        <div class="small font-weight-bold mt-2">
                                            Sozlama yoqilgan, ammo xavfsizlik pauzasi avtomatik bekor qilinmadi. Gemini krediti va AI holati sahifasini tekshiring.
                                        </div>
                                    @endif
                                </div>

                                <div class="form-group mb-0">
                                    <label for="ai_evaluations_enabled">AI tekshiruv holati</label>
                                    <select id="ai_evaluations_enabled" name="ai_evaluations_enabled"
                                            class="form-control @error('ai_evaluations_enabled') is-invalid @enderror"
                                            required>
                                        <option value="1" @selected((string) old('ai_evaluations_enabled', (int) $aiEvaluationsEnabled) === '1')>
                                            AI tekshiruvini yoqish
                                        </option>
                                        <option value="0" @selected((string) old('ai_evaluations_enabled', (int) $aiEvaluationsEnabled) === '0')>
                                            AI tekshiruvini vaqtincha o‘chirish
                                        </option>
                                    </select>
                                    @error('ai_evaluations_enabled')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save mr-1" aria-hidden="true"></i>
                                    Saqlash
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection

@section('script')
    <script src="{{ asset('plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $('#permission-user-id').select2({
            theme: 'bootstrap4',
            placeholder: 'Foydalanuvchini ism yoki HEMIS ID bo‘yicha izlang',
            width: '100%'
        });

        const permissionCriteria = $('#permission-criterion-ids').select2({
            theme: 'bootstrap4',
            placeholder: 'Bir yoki bir nechta kriteriyani tanlang',
            width: '100%'
        });

        const allCriteria = $('#permission-all-criteria');
        const toggleCriterionSelection = function () {
            permissionCriteria.prop('disabled', allCriteria.is(':checked')).trigger('change');
        };

        allCriteria.on('change', toggleCriterionSelection);
        toggleCriterionSelection();
    </script>
@endsection
