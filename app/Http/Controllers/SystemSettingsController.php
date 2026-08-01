<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUploadSettingsRequest;
use App\Models\Option;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function index(): View
    {
        Gate::authorize('manage-kpi-settings');

        return view('pages.settings.index', [
            'resourceUploadsEnabled' => Option::resourceUploadsEnabled(),
            'breadcrumbs' => [
                ['url' => route('home'), 'name' => 'Asosiy sahifa'],
                ['url' => '#', 'name' => 'Sozlamalar'],
            ],
        ]);
    }

    public function updateUploads(UpdateUploadSettingsRequest $request): RedirectResponse
    {
        $enabled = (bool) $request->validated('resource_uploads_enabled');

        Option::setResourceUploadsEnabled($enabled);

        Log::info('Global resource upload setting changed.', [
            'enabled' => $enabled,
            'user_id' => $request->user()->getKey(),
            'hemis_id' => $request->user()->hemis_id,
        ]);

        return back()->with(
            'success',
            $enabled
                ? 'Tizimga resurs yuklash qayta yoqildi.'
                : 'Tizimga resurs yuklash vaqtincha o‘chirildi.',
        );
    }
}
