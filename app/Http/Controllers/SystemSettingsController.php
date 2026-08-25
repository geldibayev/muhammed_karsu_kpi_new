<?php

namespace App\Http\Controllers;

use App\Actions\SetAiEvaluationState;
use App\Http\Requests\UpdateAiSettingsRequest;
use App\Http\Requests\UpdateUploadSettingsRequest;
use App\Jobs\ProcessAiDatumEvaluation;
use App\Models\Criterion;
use App\Models\CriterionUploadPermission;
use App\Models\Option;
use App\Models\Report;
use App\Models\User;
use App\Support\ResourceUploadWindow;
use Illuminate\Database\Eloquent\Builder;
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
        $currentReport = Report::current();
        $uploadPermissionCriteria = Criterion::query()
            ->select(['id', 'code', 'name', 'parent_id'])
            ->with('parent:id,name')
            ->whereNotNull('parent_id')
            ->where('report_id', $currentReport?->getKey() ?? 0)
            ->where('status', '1')
            ->where(fn (Builder $query): Builder => $query
                ->where('upload', '1')
                ->orWhere('code', Criterion::H_INDEX_CODE))
            ->orderBy('code')
            ->get();
        $uploadPermissionUsers = User::query()
            ->select(['id', 'hemis_id', 'name', 'rol', 'status', 'upload_blocked_at'])
            ->active()
            ->whereNull('upload_blocked_at')
            ->where(fn (Builder $query): Builder => $query
                ->whereJsonContains('rol', 'teacher')
                ->orWhereJsonContains('rol', 'user'))
            ->get()
            ->sortBy(fn (User $user): string => $user->full ?: $user->short)
            ->values();
        $uploadPermissions = CriterionUploadPermission::query()
            ->available()
            ->with([
                'user:id,hemis_id,name',
                'criterion:id,code,name',
                'grantedBy:id,name',
            ])
            ->latest()
            ->get();

        return view('pages.settings.index', [
            'resourceUploadsEnabled' => $resourceUploadsEnabled,
            'resourceUploadsAvailable' => $resourceUploadsEnabled && $resourceUploadWindowOpen,
            'resourceUploadWindowOpen' => $resourceUploadWindowOpen,
            'resourceUploadDeadlineLabel' => $resourceUploadWindow->formattedDeadline(),
            'uploadPermissionCriteria' => $uploadPermissionCriteria,
            'uploadPermissionUsers' => $uploadPermissionUsers,
            'uploadPermissions' => $uploadPermissions,
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
