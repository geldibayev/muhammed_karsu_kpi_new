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
                        {!! $upload->desc['uz'] !!}
                    </div>
                    <div class="text-danger font-weight-bold pt-2">
                        Maksimal ball:
                        {{ $upload->criterionEvaluation($upload->id, auth()->user()->degree)->score }} ball
                    </div>
                    @if($upload->file_limit > 0)
                        <div class="text-dark">
                            <div class="font-weight-bold d-inline-block">
                                Fayl yuklash chegarasi: {{ $upload->file_limit }}
                            </div>
                            <div class="small d-inline-block text-primary">
                                (Siz {{ $files->count() }}ta fayl yuklagansiz)
                            </div>
                        </div>
                    @endif
                </div>
                <div class="card-body p-0">
                    @if($upload->upload == '1')
                        @if($upload->file_limit == 0 || $files->count() < $upload->file_limit)
                            <form action="{{ route('upload.update', $upload->id) }}" method="post"
                                  enctype="multipart/form-data" id="fileForm">
                                @csrf
                                @method('PUT')
                                <div class="card-footer">
                                    <div class="row">
                                        @if($upload->template)
                                            @include('pages.users.upload.template.' . $upload->template)
                                        @endif
                                        @if($upload->res_type == 'all')
                                            <div class="col-md-2 mb-3">
                                                <label class="small mb-0">Resurs turi</label>
                                                <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                                                    <label class="btn btn-outline-primary active w-100">
                                                        <input type="radio" name="uploadResourceType" value="file"
                                                               checked>
                                                        Fayl
                                                    </label>
                                                    <label class="btn btn-outline-primary w-100">
                                                        <input type="radio" name="uploadResourceType" value="url">
                                                        URL
                                                    </label>
                                                </div>
                                            </div>

                                            <div class="col-md-8 mb-3">
                                                <label class="small mb-0">Resurs manbai</label>
                                                <div id="fileUploadBlock"
                                                     style="border: 1px solid #ddd; padding: 6px; border-radius: 5px">
                                                    <label for="uploadResourceFile" class="drag-area mb-0"
                                                           id="dragZone">
                                                <span id="file-name" class="small text-muted">
                                                    Faylga yo‘l ko‘rsating...
                                                </span>
                                                    </label>
                                                    <input type="file" id="uploadResourceFile" name="uploadResourceFile"
                                                           class="d-none" accept=".pdf,.jpg,.png,.zip,.rar" required>
                                                </div>

                                                <div id="urlUploadBlock" style="display: none;">
                                                    <input type="url" id="uploadResourceUrl" name="uploadResourceUrl"
                                                           class="form-control"
                                                           placeholder="Masalan: https://example.com/resurs.pdf">
                                                </div>

                                                <div class="text-danger small mt-1" id="limit_error"
                                                     style="display: none;">
                                                    <i class="fas fa-exclamation-triangle mr-1"></i>
                                                    Fayl limiti 2 megabaytdan oshib ketdi.
                                                </div>
                                            </div>
                                        @elseif($upload->res_type == 'url')
                                            <div class="col-md-10 mb-3">
                                                <input type="hidden" name="uploadResourceType" value="url">
                                                <label class="small mb-0">Resurs manbai</label>
                                                <input type="url" id="uploadResourceUrl" name="uploadResourceUrl"
                                                       class="form-control"
                                                       placeholder="Masalan: https://example.com/resurs.pdf" required>
                                            </div>
                                        @elseif($upload->res_type == 'file')
                                            <div class="col-md-10 mb-3">
                                                <input type="hidden" name="uploadResourceType" value="file">
                                                <label class="small mb-0">Resurs manbai</label>
                                                <input type="file" id="uploadResourceFile" name="uploadResourceFile"
                                                       class="form-control" accept=".pdf,.jpg,.png,.zip,.rar" required>
                                            </div>
                                        @endif
                                        <div class="col-md-2 mb-3">
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
                @if($upload->files->count())
                    <div class="card-footer border-top p-0">
                        <table class="table table-hover small text-center">
                            <thead>
                            <tr>
                                <th style="width: 5%;">#</th>
                                <th class="text-left">Fayl</th>
                                @if($upload->checking == 'ai')
                                    <th>AI taqriz</th>
                                @else
                                    <th>Tekshiruvchi xulosasi</th>
                                @endif
                                <th>Holati</th>
                                <th>Ball</th>
                                <th>Vaqt</th>
                                <th></th>
                            </tr>
                            </thead>
                            <tbody>
                            @foreach($upload->files as $file)
                                <tr>
                                    <td class="font-weight-bold align-middle">{{ $file->id }}</td>
                                    <td class="font-weight-bold align-middle text-left">
                                        @if($file->material['type'] == 'url')
                                            <a href="{{ $file->material['link'] }}">{{ $file->name }}</a>
                                        @else
                                            {{ $file->name }}
                                        @endif
                                    </td>
                                    <td class="align-middle">{{ $file->reason }}</td>
                                    <td class="align-middle">
                                        @if($file->status == 'received')
                                            <div class="badge badge-primary">
                                                Yangi resurs
                                            </div>
                                        @elseif($file->status == 'checking')
                                            <div class="badge badge-warning">
                                                Tekshirilmoqda
                                            </div>
                                        @elseif($file->status == 'accepted')
                                            <div class="badge badge-success">
                                                Qabul qilingan
                                            </div>
                                        @elseif($file->status == 'cancelled')
                                            <div class="badge badge-dark">
                                                Bekor qilingan
                                            </div>
                                        @endif
                                    </td>
                                    <td class="align-middle">{{ number_format($file->point ?? 0, 2) }}</td>
                                    <td class="align-middle">{{ $file->created_at->format('d.m.Y H:i') }}</td>
                                    <td>
                                        <a href="{{ route('upload.file.download', $file->id) }}"
                                           class="btn btn-xs btn-outline-primary"><i
                                                class="fas fa-download m-1"></i></a>
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
            var emptyText = "Faylga yo'l ko'rsatilmagan...";
            @if($upload->res_type == 'all')
            // Radio almashinishi
            $('input[name="uploadResourceType"]').on('change', function () {
                if ($(this).val() === 'file') {
                    $fileBlock.show();
                    $urlBlock.hide();
                    $fileInput.prop('required', true);
                    $urlInput.prop('required', false).val('');
                } else {
                    $urlBlock.show();
                    $fileBlock.hide();
                    $urlInput.prop('required', true);
                    $fileInput.prop('required', false).val('');
                    $limitError.hide();
                    $submitBtn.prop('disabled', false);
                }
            });

            if ($('input[name="uploadResourceType"]:checked').val() === 'url') {
                $('input[name="uploadResourceType"][value="url"]').trigger('change');
            }

            // Drag & Drop hodisalari
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

            // Input orqali tanlanganda
            $fileInput.on('change', function () {
                if (this.files.length > 0) {
                    validateAndShowFile(this.files[0]);
                } else {
                    resetFile();
                }
            });

            function validateAndShowFile(file) {
                var maxSize = 2 * 1024 * 1024; // 2 MB
                $fileName.html('<strong>' + file.name + '</strong>');

                if (file.size > maxSize) {
                    $submitBtn.prop('disabled', true);
                    $limitError.show();
                    $dragZone.css('border-color', '#dc3545'); // Xato rangi
                } else {
                    $submitBtn.prop('disabled', false);
                    $limitError.hide();
                    $dragZone.css('border-color', '#28a745'); // Muvaffaqiyat rangi
                }
            }

            function resetFile() {
                $fileName.text(emptyText);
                $submitBtn.prop('disabled', false);
                $limitError.hide();
                $dragZone.css('border-color', '#ced4da');
            }

            // Yuklash animatsiyasi
            $('#fileForm').on('submit', function () {
                $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Yuklanmoqda...');
            });
            @endif
        });
    </script>
@endsection
