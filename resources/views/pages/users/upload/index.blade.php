@extends('layouts.app')

@section('style')
    <style>
        /* Soddalashtirilgan Drag & Drop maydoni */
        .drag-area {
            border: 2px dashed #ced4da;
            border-radius: 4px;
            padding: 20px;
            text-align: center;
            background-color: #f8f9fa;
            cursor: pointer;
            transition: all 0.2s ease;
            display: block;
        }

        .drag-area:hover, .drag-area.active {
            border-color: #80bdff;
            background-color: #f1f8ff;
        }

        .drag-area i {
            color: #adb5bd;
            font-size: 24px;
            margin-bottom: 8px;
        }

        #global-drag-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 123, 255, 0.9);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            color: white;
            border: 8px dashed rgba(255, 255, 255, 0.6);
            box-sizing: border-box;
        }

        #global-drag-overlay.active {
            display: flex;
        }

        #global-drag-overlay .overlay-content {
            text-align: center;
            pointer-events: none;
        }
    </style>
@endsection

@section('content')
    <section class="content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <div class="font-weight-bold" style="text-align: justify">
                        {{ $upload->name['uz'] }}
                    </div>
                    <div style="text-align: justify" class="small">
                        @php($description = strip_tags(str_ireplace(['<br>', '<br/>', '<br />'], "\n", data_get($upload->desc, 'uz', ''))))
                        <div style="white-space: pre-line">{{ $description }}</div>
                    </div>
                    <div class="text-danger font-weight-bold pt-2">
                        Maksimal ball:
                        {{ $upload->criterionEvaluation($upload->id, auth()->user()->degree)?->score ?? 0 }} ball
                    </div>
                    @if($upload->file_limit > 0)
                        <div class="text-dark">
                            <div class="font-weight-bold d-inline-block">
                                Fayl yuklash chegarasi: {{ $upload->file_limit }}
                            </div>
                            <div class="small d-inline-block text-primary">
                                (Siz {{ $files }}ta resurs yuklagansiz)
                            </div>
                        </div>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($upload->usesHIndexSubmission())
                        <form action="{{ route('upload.store', $upload) }}" method="post" id="hIndexForm">
                            @csrf
                            <input type="hidden" name="uploadResourceType" value="h_index">
                            <div class="card-footer">
                                <div class="alert alert-info small">
                                    Har bir bazadagi profilingiz havolasi va h-index qiymatini kiriting. Ma’lumotlar mas’ul tekshiruviga yuboriladi.
                                </div>
                                <div class="row">
                                    @foreach([
                                        'scopus' => 'Scopus',
                                        'web_of_science' => 'Web of Science',
                                        'research_gate' => 'Research Gate',
                                    ] as $profileKey => $profileLabel)
                                        <div class="col-md-4 mb-3">
                                            <label class="font-weight-bold">{{ $profileLabel }}</label>
                                            <input type="url" name="h_index[{{ $profileKey }}][link]"
                                                   class="form-control mb-2" placeholder="Profil havolasi"
                                                   value="{{ old('h_index.'.$profileKey.'.link') }}">
                                            <input type="number" name="h_index[{{ $profileKey }}][value]"
                                                   class="form-control" min="0" placeholder="h-index"
                                                   value="{{ old('h_index.'.$profileKey.'.value') }}">
                                        </div>
                                    @endforeach
                                </div>
                                <div class="text-muted small">Kamida bitta platformada link va h-index qiymatini to'liq kiriting.</div>
                                <div id="hIndexProfileError" class="text-danger small mt-1 d-none"></div>
                                <div class="row">
                                    <div class="col-md-4 ml-auto">
                                        <label class="small mb-0" for="h_index_year_id">Resurs yili</label>
                                        <select name="year" id="h_index_year_id" class="form-control" required>
                                            <option selected disabled value=""></option>
                                            @foreach($years as $year)
                                                <option value="{{ $year->id }}">{{ $year->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-success btn-block mt-3">
                                    <i class="fas fa-save mr-1"></i> Saqlash va mas’ulga yuborish
                                </button>
                            </div>
                        </form>
                    @elseif($upload->upload == '1')
                        @if($upload->file_limit == 0 || $files < $upload->file_limit)
                            @php($allowedResourceTypes = $upload->allowedSubmissionResourceTypes())
                            <form action="{{ route('upload.store', $upload) }}" method="post"
                                  enctype="multipart/form-data" id="fileForm">
                                @csrf
                                <div class="card-footer">
                                    <div class="row">
                                        @if($upload->template)
                                            @include('pages.users.upload.template.' . $upload->template)
                                        @endif

                                        @if(count($allowedResourceTypes) > 1)
                                            <div class="col-md-2 mb-2">
                                                <label class="small mb-0">Resurs turi</label>
                                                <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                                    <label class="btn btn-outline-primary active w-100">
                                                        <input type="radio" name="uploadResourceType" value="file"
                                                               checked> Fayl
                                                    </label>
                                                    <label class="btn btn-outline-primary w-100">
                                                        <input type="radio" name="uploadResourceType" value="url"> URL
                                                    </label>
                                                </div>
                                            </div>
                                        @else
                                            <input type="hidden" name="uploadResourceType"
                                                   value="{{ $allowedResourceTypes[0] }}">
                                        @endif

                                        <div class="{{ count($allowedResourceTypes) > 1 ? 'col-md-8' : 'col-md-10' }} mb-2">
                                            <label class="small mb-0">Resurs manbai</label>
                                            @if($upload->checking === 'ai')
                                                <div class="small text-muted mb-1">
                                                    AI tekshiruvi uchun PDF, JPG, JPEG yoki PNG fayl yuklang.
                                                </div>
                                            @endif

                                            @if(in_array('file', $allowedResourceTypes, true))
                                                <div id="fileUploadBlock"
                                                     style="border: 1px solid #ddd; padding: 6px; border-radius: 5px; display: block;">
                                                    <label for="uploadResourceFile" class="drag-area mb-0"
                                                           id="dragZone">
                                                        <span id="file-name" class="small text-muted">
                                                            Faylga yo‘l ko‘rsating...
                                                        </span>
                                                    </label>
                                                    <input type="file" id="uploadResourceFile" name="uploadResourceFile"
                                                           class="d-none"
                                                           accept=".pdf,.jpg,.jpeg,.png" {{ count($allowedResourceTypes) === 1 ? 'required' : '' }}>
                                                </div>
                                                <div class="text-danger small mt-1" id="limit_error"
                                                     style="display: none;">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    Fayl hajmi {{ (int) config('kpi.upload_max_file_size_mb', 5) }} MB
                                                    dan oshmasligi kerak.
                                                </div>
                                            @endif

                                            @if(in_array('url', $allowedResourceTypes, true))
                                                <div id="urlUploadBlock"
                                                     style="display: {{ count($allowedResourceTypes) === 1 ? 'block' : 'none' }};">
                                                    <input type="url" id="uploadResourceUrl" name="uploadResourceUrl"
                                                           class="form-control"
                                                           placeholder="Masalan: https://example.com/resurs.pdf" {{ count($allowedResourceTypes) === 1 ? 'required' : '' }}>
                                                </div>
                                            @endif
                                        </div>

                                        <div class="col-md-2 mb-2">
                                            <label class="small mb-0" for="year_id">Resurs yili</label>
                                            <select name="year" id="year_id" class="form-control" required>
                                                <option selected disabled value=""></option>
                                                @foreach($years as $year)
                                                    <option value="{{ $year->id }}">{{ $year->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>

                                    <div class="text-center mt-2">
                                        <button type="submit" id="btn_submit" class="btn btn-success w-100">
                                            <i class="fas fa-upload mr-1"></i> Yuklash
                                        </button>
                                    </div>
                                </div>
                            </form>
                        @else
                            <div class="text-center text-danger p-3 small">
                                Mezonga resurs kiritish bo‘yicha belgilangan chegaradan ko‘p resurs kiritib bo‘lmaydi.
                            </div>
                        @endif
                    @else
                        <div class="text-center text-danger p-3 small">
                            Mezonga resurs kiritish taqiqlangan, tizim administrator bilan bog'laning.
                        </div>
                    @endif
                </div>

                @if($submissions->isNotEmpty())
                    <div class="card-footer border-top p-0">
                        <table class="table table-hover small text-center">
                            <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th class="text-left">Fayl</th>
                                <th>{{ $upload->checking == 'ai' ? 'AI taqriz' : 'Tekshiruvchi xulosasi' }}</th>
                                <th>Holati</th>
                                <th>Ball</th>
                                <th>Vaqt</th>
                                <th style="width: 8%"></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($submissions as $file)
                                <tr>
                                    <td class="font-weight-bold align-middle">{{ $file->id }}</td>
                                    <td class="font-weight-bold align-middle text-left">
                                        @if($file->material['type'] == 'url')
                                            <a href="{{ $file->material['link'] }}" target="_blank"
                                               rel="noopener noreferrer">{{ $file->name }}</a>
                                        @else
                                            {{ $file->name }}
                                        @endif
                                    </td>
                                    <td class="align-middle">{{ $file->reason }}</td>
                                    <td class="align-middle">
                                        @if($file->status == 'received')
                                            <div class="badge badge-primary">Yangi resurs</div>
                                        @elseif($file->status == 'checking')
                                            <div class="badge badge-warning">Tekshirilmoqda</div>
                                        @elseif($file->status == 'accepted')
                                            <div class="badge badge-success">Qabul qilingan</div>
                                        @elseif($file->status == 'cancelled')
                                            <div class="badge badge-dark">Bekor qilingan</div>
                                        @endif
                                    </td>
                                    <td class="align-middle">{{ number_format($file->point ?? 0, 2) }}</td>
                                    <td class="align-middle">{{ $file->created_at->format('d.m.Y H:i') }}</td>
                                    <td>
                                        @if($file->material['type'] !== 'h_index')
                                            <a href="{{ route('upload.file.download', $file->id) }}"
                                               class="btn btn-xs btn-outline-primary">
                                                <i class="fas fa-download m-1"></i>
                                            </a>
                                        @endif
                                        <form action="{{ route('upload.destroy', $file->id) }}" method="POST"
                                              class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-outline-dark btn-xs"
                                                    onclick="return confirm('Resursni o‘chirishni xohlaysizmi?')">
                                                <i class="fa fa-trash m-1"></i>
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
        </div>
    </section>

    <div id="global-drag-overlay" style="display: none;">
        <div class="overlay-content">
            <i class="fas fa-cloud-upload-alt fa-5x mb-3"></i>
            <h2 class="font-weight-bold">Faylni shu yerga tashlang</h2>
        </div>
    </div>
@endsection

@section('script')
    <script src="{{ asset('/plugins/select2/js/select2.full.min.js') }}"></script>
    <script>
        $(document).ready(function () {
            $('.select2bs4').select2({theme: 'bootstrap4'});

            var $fileBlock = $('#fileUploadBlock');
            var $urlBlock = $('#urlUploadBlock');
            var $limitError = $('#limit_error');
            var $fileInput = $('#uploadResourceFile');
            var $urlInput = $('#uploadResourceUrl');
            var $submitBtn = $('#btn_submit');
            var $fileName = $('#file-name');
            var $dragZone = $('#dragZone');
            var $globalOverlay = $('#global-drag-overlay');
            var dragTimer;
            var emptyText = "Faylga yo'l ko'rsatilmagan...";

            // Tizim faqat URL, faqat Fayl yoki Ikkalasini so'rayotganini tekshiramiz
            var hasRadios = $('input[name="uploadResourceType"][type="radio"]').length > 0;

            function isFileModeActive() {
                if (hasRadios) {
                    return $('input[name="uploadResourceType"]:checked').val() === 'file';
                }
                return $('input[name="uploadResourceType"]').val() === 'file';
            }

            // Radio Button almashuv logikasi
            if (hasRadios) {
                $('input[name="uploadResourceType"]').on('change', function () {
                    if ($(this).val() === 'file') {
                        $fileBlock.show();
                        $urlBlock.hide();
                        if ($fileInput.length) $fileInput.prop('required', true);
                        if ($urlInput.length) $urlInput.prop('required', false).val('');
                    } else {
                        $urlBlock.show();
                        $fileBlock.hide();
                        if ($urlInput.length) $urlInput.prop('required', true);
                        if ($fileInput.length) $fileInput.prop('required', false).val('');
                        $limitError.hide();
                        $submitBtn.prop('disabled', false);
                    }
                });

                // Boshlang'ich holat uchun trigger
                $('input[name="uploadResourceType"]:checked').trigger('change');
            }

            // --- 1. GLOBAL DRAG & DROP HODISALARI ---
            $(document).on('dragover dragenter', function (e) {
                e.preventDefault();
                e.stopPropagation();

                if (isFileModeActive() && $fileInput.length > 0) {
                    $globalOverlay.addClass('active');
                    clearTimeout(dragTimer);
                }
            });

            $(document).on('dragleave dragend', function (e) {
                e.preventDefault();
                e.stopPropagation();

                dragTimer = setTimeout(function () {
                    $globalOverlay.removeClass('active');
                }, 50);
            });

            $(document).on('drop', function (e) {
                e.preventDefault();
                e.stopPropagation();
                $globalOverlay.removeClass('active');

                if (isFileModeActive() && $fileInput.length > 0) {
                    var files = e.originalEvent.dataTransfer.files;
                    if (files.length > 0) {
                        $fileInput[0].files = files;
                        validateAndShowFile(files[0]);
                    }
                }
            });

            // --- 2. LOCAL DRAG & DROP VA INPUT HODISALARI ---
            if ($dragZone.length > 0) {
                $dragZone.on('dragover dragenter', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('active');
                });

                $dragZone.on('dragleave dragend drop', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('active');
                });

                $dragZone.on('drop', function (e) {
                    var files = e.originalEvent.dataTransfer.files;
                    if (files.length > 0) {
                        $fileInput[0].files = files;
                        validateAndShowFile(files[0]);
                    }
                });
            }

            if ($fileInput.length > 0) {
                $fileInput.on('change', function () {
                    if (this.files.length > 0) {
                        validateAndShowFile(this.files[0]);
                    } else {
                        resetFile();
                    }
                });
            }

            // --- 3. FAYLNI TEKSHIRISH FUNKSIYALARI ---
            function validateAndShowFile(file) {
                var maxSize = {{ (int) config('kpi.upload_max_file_size_mb', 5) }} * 1024 * 1024;
                $fileName.html('<strong>' + file.name + '</strong>');

                if (file.size > maxSize) {
                    $submitBtn.prop('disabled', true);
                    $limitError.show();
                    if ($dragZone.length) $dragZone.css('border-color', '#dc3545');
                } else {
                    $submitBtn.prop('disabled', false);
                    $limitError.hide();
                    if ($dragZone.length) $dragZone.css('border-color', '#28a745');
                }
            }

            function resetFile() {
                $fileName.text(emptyText);
                $submitBtn.prop('disabled', false);
                $limitError.hide();
                if ($dragZone.length) $dragZone.css('border-color', '#ced4da');
            }

            var $hIndexForm = $('#hIndexForm');
            if ($hIndexForm.length) {
                var hIndexProfiles = ['scopus', 'web_of_science', 'research_gate'];

                function hasValue(value) {
                    return (value || '').toString().trim() !== '';
                }

                function getProfileValues(profile) {
                    return {
                        link: $hIndexForm.find('input[name="h_index[' + profile + '][link]"]').val(),
                        value: $hIndexForm.find('input[name="h_index[' + profile + '][value]"]').val(),
                    };
                }

                function hasCompleteProfile() {
                    return hIndexProfiles.some(function (profile) {
                        var data = getProfileValues(profile);

                        return hasValue(data.link) && hasValue(data.value);
                    });
                }

                function hasPartialProfile() {
                    return hIndexProfiles.some(function (profile) {
                        var data = getProfileValues(profile);
                        var linkFilled = hasValue(data.link);
                        var valueFilled = hasValue(data.value);

                        return linkFilled !== valueFilled;
                    });
                }

                function clearProfileValidation() {
                    $('#hIndexProfileError').addClass('d-none').text('');
                    hIndexProfiles.forEach(function (profile) {
                        $hIndexForm.find('input[name="h_index[' + profile + '][link]"]').removeClass('is-invalid');
                        $hIndexForm.find('input[name="h_index[' + profile + '][value]"]').removeClass('is-invalid');
                    });
                }

                $hIndexForm.on('submit', function () {
                    clearProfileValidation();

                    if (!hasCompleteProfile()) {
                        var $error = $('#hIndexProfileError');

                        if (hasPartialProfile()) {
                            $error.text('Har bir tanlangan platforma uchun link va h-index qiymatini birga kiriting.');
                        } else {
                            $error.text('Iltimos, kamida bitta platforma uchun to\'liq profil kiriting.');
                        }

                        $error.removeClass('d-none');

                        hIndexProfiles.forEach(function (profile) {
                            var data = getProfileValues(profile);

                            if (hasValue(data.link) !== hasValue(data.value)) {
                                $hIndexForm.find('input[name="h_index[' + profile + '][link]"]').addClass('is-invalid');
                                $hIndexForm.find('input[name="h_index[' + profile + '][value]"]').addClass('is-invalid');
                            }
                        });

                        return false;
                    }
                });
            }

            // Yuklash animatsiyasi
            $('#fileForm').on('submit', function () {
                $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Yuklanmoqda...');
            });
        });
    </script>
@endsection
