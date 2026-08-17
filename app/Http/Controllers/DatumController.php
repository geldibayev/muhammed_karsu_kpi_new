<?php

namespace App\Http\Controllers;

use App\Actions\CreateDatumSubmission;
use App\Actions\ResolveAiManualPointMaximum;
use App\Enums\DatumStatus;
use App\Http\Requests\StoreDatumRequest;
use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Language;
use App\Support\EducationalContentCriterionRule;
use App\Support\ForeignLanguageCertificateCriterionRule;
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

        $years = $upload->years()
            ->where('status', '1')
            ->orderBy('name')
            ->get();
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
        $files = $submissions
            ->whereIn('status', Datum::statusesCountingTowardsUploadLimit())
            ->count();

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

    public function details(Datum $datum, ResolveAiManualPointMaximum $maximumResolver): View
    {
        $this->authorize('view', $datum);

        $decisionOverridePointMaximum = (auth()->user()?->can('overrideCancellation', $datum) === true
            || auth()->user()?->can('updateAcceptedScore', $datum) === true)
            ? $maximumResolver->handle($datum)
            : null;

        $datum->load([
            'criterion:id,code,name,checking,report_id',
            'criterion.criterionEvaluations:id,criterion_id,evaluation,has,score',
            'criterion.manualScoreOptions',
            'duplicateOf:id,name,status',
            'manualScoreOption:id,criterion_id,code,label',
            'user:id,name,degree',
            'user.ratingWorkplace.department:id,parent_id',
            'year:id,name',
            'histories' => fn ($query) => $query->latest('id'),
        ]);
        $datum->setRelation(
            'histories',
            $datum->histories
                ->filter(fn ($history): bool => $history->isVisibleToSubmitter())
                ->values(),
        );
        $matchingIndustryFundingSubmissions = auth()->user()?->can('overrideAcceptance', $datum) === true
            ? $datum->matchingIndustryFundingSubmissions()
            : collect();
        $status = DatumStatus::from($datum->status);
        $educationalContentTypeOptions = collect();
        $educationalContentTypeDuplicate = false;
        $educationalContentMaximum = null;
        $foreignLanguageCertificateOptions = collect();
        $foreignLanguageCertificatePoints = [];

        $canChooseEducationalContentType = $datum->criterion?->code === EducationalContentCriterionRule::CODE
            && (auth()->user()?->can('changeEducationalContentType', $datum) === true
                || auth()->user()?->can('overrideCancellation', $datum) === true);

        if ($canChooseEducationalContentType) {
            $usedOptionIds = Datum::query()
                ->where('user_id', $datum->user_id)
                ->where('criterion_id', $datum->criterion_id)
                ->where('status', DatumStatus::Accepted->value)
                ->where('id', '!=', $datum->getKey())
                ->whereNotNull('manual_score_option_id')
                ->pluck('manual_score_option_id');
            $educationalContentTypeOptions = $datum->criterion->manualScoreOptions
                ->filter(fn ($option): bool => EducationalContentCriterionRule::percentageFor($option->code) !== null)
                ->reject(fn ($option): bool => $usedOptionIds->contains($option->getKey()))
                ->values();
            $educationalContentTypeDuplicate = $datum->manual_score_option_id !== null
                && $usedOptionIds->contains($datum->manual_score_option_id);
            $educationalContentMaximum = (float) $datum->criterion->criterionEvaluations
                ->firstWhere('evaluation', $datum->user->degree)?->score;
        }

        if ($datum->criterion?->code === ForeignLanguageCertificateCriterionRule::CODE
            && auth()->user()?->can('overrideCancellation', $datum) === true) {
            $department = $datum->user?->ratingWorkplace?->department;
            $foreignLanguageCertificateOptions = $datum->criterion->manualScoreOptions
                ->whereIn('code', array_keys(ForeignLanguageCertificateCriterionRule::LEVEL_LABELS))
                ->values();
            $foreignLanguageCertificatePoints = $foreignLanguageCertificateOptions
                ->mapWithKeys(fn ($option): array => [
                    $option->getKey() => ForeignLanguageCertificateCriterionRule::pointFor(
                        $option->code,
                        (string) $datum->user?->degree,
                        $department?->getKey(),
                        $department?->parent_id,
                    ),
                ])->all();
        }
        $breadcrumbs = [
            [
                'url' => route('home'),
                'name' => 'Asosiy sahifa',
            ],
            [
                'url' => route('files.show', $status),
                'name' => $status->label().' resurslar',
            ],
            [
                'url' => '#',
                'name' => 'Resurs #'.$datum->id,
            ],
        ];

        return view('pages.users.submissions.show', compact(
            'datum',
            'status',
            'breadcrumbs',
            'decisionOverridePointMaximum',
            'educationalContentTypeOptions',
            'educationalContentTypeDuplicate',
            'educationalContentMaximum',
            'foreignLanguageCertificateOptions',
            'foreignLanguageCertificatePoints',
            'matchingIndustryFundingSubmissions',
        ));
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
                'reviewer_hemis_id' => null,
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
