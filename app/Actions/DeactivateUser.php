<?php

namespace App\Actions;

use App\Models\CriterionPoint;
use App\Models\Datum;
use App\Models\Point;
use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class DeactivateUser
{
    public function __construct(private RecalculateReportPoints $recalculateReportPoints) {}

    public function handle(User $actor, User $target, bool $deleteStoredFiles = false): void
    {
        [$reportIds, $storedFiles] = DB::transaction(
            fn (): array => $this->deactivate($actor, $target),
            attempts: 5,
        );

        if ($deleteStoredFiles) {
            $this->deleteStoredFiles($storedFiles);
        }

        Report::query()
            ->whereKey($reportIds)
            ->get()
            ->each(function (Report $report): void {
                $this->recalculateReportPoints->handle($report);
            });
    }

    /**
     * @return array{0: Collection<int, int>, 1: Collection<int, array{disk: string, path: string}>}
     */
    private function deactivate(User $actor, User $target): array
    {
        $lockedUser = User::query()
            ->whereKey($target->getKey())
            ->lockForUpdate()
            ->firstOrFail();

        Gate::forUser($actor)->authorize('deactivate', $lockedUser);

        $submissions = $lockedUser->submissions()
            ->with('criterion:id,report_id')
            ->lockForUpdate()
            ->get();
        $reportIds = $submissions->pluck('criterion.report_id')
            ->merge($lockedUser->points()->pluck('report_id'))
            ->merge($lockedUser->criterionPoints()->pluck('report_id'))
            ->filter()
            ->map(fn (mixed $reportId): int => (int) $reportId)
            ->unique()
            ->values();
        $storedFiles = $submissions
            ->filter(fn (Datum $datum): bool => $datum->status !== 'deleted'
                && $datum->storagePath() !== null)
            ->map(fn (Datum $datum): array => [
                'disk' => $datum->storageDisk(),
                'path' => (string) $datum->storagePath(),
            ])
            ->values();

        $submissions
            ->where('status', '!=', 'deleted')
            ->each(function (Datum $datum) use ($actor): void {
                $datum->update([
                    'status' => 'deleted',
                    'point' => 0,
                    'reviewer_hemis_id' => null,
                    'reason' => 'Foydalanuvchi administrator tomonidan faolsizlantirildi.',
                ]);
                $datum->histories()->create([
                    'user_id' => $actor->getKey(),
                    'type' => 'info',
                    'message' => 'Foydalanuvchi kafedra yoki fakultetda faol ishlamagani sabab faolsizlantirildi; resurs va uning balli barcha reytinglardan chiqarildi.',
                    'message_type' => 'user_deactivated',
                ]);
            });

        $lockedUser->submissions()
            ->where('point', '!=', 0)
            ->update(['point' => 0]);
        $lockedUser->submissions()
            ->whereHas('resourceIdentifiers')
            ->each(fn (Datum $datum): int => $datum->resourceIdentifiers()->update(['active_value_hash' => null]));
        Point::query()->whereBelongsTo($lockedUser)->delete();
        CriterionPoint::query()->whereBelongsTo($lockedUser)->delete();

        if (Schema::hasTable((string) config('session.table', 'sessions'))) {
            DB::table((string) config('session.table', 'sessions'))
                ->where('user_id', $lockedUser->getKey())
                ->delete();
        }

        $lockedUser->forceFill([
            'status' => '0',
            'remember_token' => null,
        ])->save();

        return [$reportIds, $storedFiles];
    }

    /** @param Collection<int, array{disk: string, path: string}> $storedFiles */
    private function deleteStoredFiles(Collection $storedFiles): void
    {
        $storedFiles->each(function (array $storedFile): void {
            try {
                if (! Storage::disk($storedFile['disk'])->delete($storedFile['path'])) {
                    Log::warning('Faolsizlantirilgan foydalanuvchi resursining jismoniy faylini o‘chirib bo‘lmadi.', $storedFile);
                }
            } catch (Throwable $exception) {
                Log::warning('Faolsizlantirilgan foydalanuvchi resursining jismoniy faylini o‘chirishda xato yuz berdi.', [
                    ...$storedFile,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }
}
