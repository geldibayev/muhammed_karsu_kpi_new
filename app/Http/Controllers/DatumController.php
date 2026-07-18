<?php

namespace App\Http\Controllers;

use App\Actions\CreateDatumSubmission;
use App\Http\Requests\StoreDatumRequest;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Language;
use App\Models\Year;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DatumController extends Controller
{
    public function show(Criterion $upload): View
    {
        $this->authorize('submit', $upload);

        $years = Year::query()->where('status', '1')->get();
        $breadcrumbs = [
            [
                'url' => route('home'),
                'name' => 'Asosiy sahifa',
            ],
            [
                'url' => '#',
                'name' => mb_substr($upload->name['uz'], 0, 30, 'UTF-8').'...',
            ],
        ];
        $languages = Language::query()->get();
        $submissions = $upload->files()
            ->whereBelongsTo(auth()->user())
            ->where('status', '!=', 'deleted')
            ->latest()
            ->get();
        $files = $submissions->count();

        return view('pages.users.upload.index', compact(
            'upload',
            'years',
            'languages',
            'breadcrumbs',
            'submissions',
            'files',
        ));
    }

    public function store(
        StoreDatumRequest $request,
        Criterion $upload,
        CreateDatumSubmission $action,
    ): RedirectResponse {
        $action->handle($request->user(), $upload, $request->validated());

        return redirect()
            ->route('upload.show', $upload)
            ->with('success', 'Resurs muvaffaqiyatli yuklandi.');
    }

    public function download(Datum $datum): StreamedResponse|RedirectResponse
    {
        $this->authorize('download', $datum);

        $path = $datum->storagePath();

        if ($path !== null && Storage::disk($datum->storageDisk())->exists($path)) {
            return Storage::disk($datum->storageDisk())->download($path, $datum->name);
        }

        return back()->with('error', 'Fayl topilmadi!');
    }

    public function destroy(Datum $datum): RedirectResponse
    {
        $this->authorize('delete', $datum);

        $path = $datum->storagePath();
        $disk = $datum->storageDisk();

        DB::transaction(function () use ($datum): void {
            $lockedDatum = Datum::query()->lockForUpdate()->findOrFail($datum->id);
            $lockedDatum->update([
                'status' => 'deleted',
                'point' => 0,
                'reason' => 'Resurs foydalanuvchi tomonidan o\'chirildi.',
            ]);
            $lockedDatum->histories()->create([
                'user_id' => auth()->id(),
                'type' => 'info',
                'message' => 'Resurs foydalanuvchi tomonidan o\'chirildi.',
                'message_type' => 'submission_deleted',
            ]);
        }, 3);

        if ($path !== null) {
            Storage::disk($disk)->delete($path);
        }

        return back()->with('success', 'Resurs muvaffaqiyatli o\'chirildi.');
    }
}
