<?php

namespace App\Actions;

use App\Models\Criterion;
use App\Models\Datum;
use App\Models\Report;
use App\Support\OakArticleCriterionRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class EnforceOakArticleFileLimit
{
    private const FILE_LIMIT = 4;

    /** @return array{accepted: int, affected_users: int, excess: int} */
    public function analyse(Report $report): array
    {
        return $this->result($this->acceptedData($this->criterion($report)));
    }

    /** @return array{accepted: int, affected_users: int, excess: int} */
    public function handle(Report $report): array
    {
        return DB::transaction(function () use ($report): array {
            Report::query()->whereKey($report->getKey())->lockForUpdate()->firstOrFail();
            $criterion = $this->criterion($report, true);
            $data = $this->acceptedData($criterion, true);
            $excessData = $this->excessData($data);
            $result = $this->result($data);

            foreach ($excessData as $datum) {
                $oldPoint = $datum->point;
                $message = '3.1.1 kriteriyasidagi 4 ta resurs cheklovi qo‘llandi. '
                    .'Yuqori balli 4 ta tasdiqlangan resurs qoldirildi. '
                    .'Ushbu resurs limitdan oshgani uchun rad etildi. Oldingi ball: '
                    .number_format($oldPoint, 4, '.', '').'.';

                $datum->update([
                    'status' => 'cancelled',
                    'point' => 0,
                    'reviewer_hemis_id' => null,
                    'reason' => $message,
                ]);
                $datum->histories()->create([
                    'user_id' => $datum->user_id,
                    'type' => 'warning',
                    'message' => $message,
                    'message_type' => 'oak_article_file_limit_enforced',
                ]);
            }

            return $result;
        }, 3);
    }

    private function criterion(Report $report, bool $lockForUpdate = false): Criterion
    {
        $criterion = Criterion::query()
            ->whereBelongsTo($report)
            ->where('code', OakArticleCriterionRule::CODE)
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->first();

        if ($criterion === null) {
            throw new RuntimeException('Tanlangan hisobotda 3.1.1 kriteriyasi topilmadi.');
        }

        if ((int) $criterion->file_limit !== self::FILE_LIMIT) {
            throw new RuntimeException('3.1.1 kriteriyasining fayl cheklovi 4 qilib sozlanmagan.');
        }

        return $criterion;
    }

    /** @return Collection<int, Datum> */
    private function acceptedData(Criterion $criterion, bool $lockForUpdate = false): Collection
    {
        return Datum::query()
            ->select(['id', 'user_id', 'criterion_id', 'status', 'point', 'reason', 'reviewer_hemis_id'])
            ->whereBelongsTo($criterion)
            ->where('status', 'accepted')
            ->orderBy('user_id')
            ->orderByDesc('point')
            ->orderBy('id')
            ->when($lockForUpdate, fn (Builder $query): Builder => $query->lockForUpdate())
            ->get();
    }

    /**
     * @param  Collection<int, Datum>  $data
     * @return Collection<int, Datum>
     */
    private function excessData(Collection $data): Collection
    {
        return $data
            ->groupBy('user_id')
            ->flatMap(fn (Collection $userData): Collection => $userData->skip(self::FILE_LIMIT));
    }

    /**
     * @param  Collection<int, Datum>  $data
     * @return array{accepted: int, affected_users: int, excess: int}
     */
    private function result(Collection $data): array
    {
        $excessData = $this->excessData($data);

        return [
            'accepted' => $data->count(),
            'affected_users' => $excessData->pluck('user_id')->unique()->count(),
            'excess' => $excessData->count(),
        ];
    }
}
