@extends('layouts.app')

@section('content')
    <section class="content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-8">
                    <div class="card card-outline {{ $resourceUploadsEnabled ? 'card-success' : 'card-warning' }} shadow-sm">
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
                                <div class="alert {{ $resourceUploadsEnabled ? 'alert-success' : 'alert-warning' }}">
                                    <div class="font-weight-bold">
                                        Joriy holat:
                                        {{ $resourceUploadsEnabled ? 'Yuklashga ruxsat berilgan' : 'Yuklash vaqtincha o‘chirilgan' }}
                                    </div>
                                    <div class="small mt-1">
                                        O‘chirilganda foydalanuvchilar fayl, URL va H-index ma’lumotlarini tizimga yubora olmaydi.
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
                </div>
            </div>
        </div>
    </section>
@endsection
