<?php

namespace App\Http\Controllers;

use App\Actions\SetAiEvaluationState;
use App\Http\Requests\UpdateAiSettingsRequest;
use App\Http\Requests\UpdateUploadSettingsRequest;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Option;
use App\Support\ResourceUploadWindow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\View\View;

class SystemSettingsController extends Controller
{
    public function index(ResourceUploadWindow $resourceUploadWindow): View
    {
        Gate::authorize('manage-kpi-settings');
        $connection = (string) config('queue.default');
        $resourceUploadsEnabled = Option::resourceUploadsEnabled();
        $resourceUploadWindowOpen = $resourceUploadWindow->isOpen();

        return view('pages.settings.index', [
            'resourceUploadsEnabled' => $resourceUploadsEnabled,
            'resourceUploadsAvailable' => $resourceUploadsEnabled && $resourceUploadWindowOpen,
            'resourceUploadWindowOpen' => $resourceUploadWindowOpen,
            'resourceUploadDeadlineLabel' => $resourceUploadWindow->formattedDeadline(),
            'aiEvaluationsEnabled' => Option::aiEvaluationsEnabled(),
            'aiQueuePaused' => Queue::isPaused($connection, ProcessAiDatumEvaluation::QUEUE),
            'aiQueuePausedBySetting' => Option::aiQueuePausedBySetting() === true,
            'aiQueuePausedReason' => Cache::get('kpi:ai-worker:paused-reason'),
            'breadcrumbs' => [
                ['url' => route('home'), 'name' => 'Asosiy sahifa'],
                ['url' => '#', 'name' => 'Sozlamalar'],
            ],
        ]);
    }

    public function updateAi(
        UpdateAiSettingsRequest $request,
        SetAiEvaluationState $setAiEvaluationState,
    ): RedirectResponse {
        $enabled = (bool) $request->validated('ai_evaluations_enabled');

        $state = $setAiEvaluationState->handle($enabled);

        Log::info('Global AI evaluation setting changed.', [
            'enabled' => $enabled,
            'queue_paused' => $state['queue_paused'],
            'queue_resumed' => $state['queue_resumed'],
            'user_id' => $request->user()->getKey(),
            'hemis_id' => $request->user()->hemis_id,
        ]);

        return back()->with(
            'success',
            match (true) {
                ! $enabled => 'AI tekshiruvi vaqtincha o\'chirildi. Navbatdagi resurslar saqlanib qoladi.',
                $state['queue_paused'] => 'AI sozlamasi yoqildi, lekin AI navbati tizim yoki Gemini krediti sabab pauzada qolmoqda.',
                default => 'AI tekshiruvi qayta yoqildi va navbat davom ettirildi.',
            },
        );
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
