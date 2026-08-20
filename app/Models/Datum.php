<?php

namespace App\Models;

use App\Enums\DatumStatus;
use App\Services\DatumResourceFingerprintGenerator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Datum extends Model
{
    public const PUBLIC_CHECKING_REASON = 'Resurs bo‘yicha yakuniy qaror kutilmoqda.';

    protected $table = 'data';

    protected $fillable = [
        'id', 'name', 'material', 'user_id', 'criterion_id', 'system_key', 'reviewer_hemis_id', 'status', 'year_id', 'language_id',
        'point', 'author_count', 'page_count', 'impact_factor', 'publication_tier', 'university_tier', 'reason',
        'received_amount', 'duplicate_of_id', 'manual_score_option_id',
    ];

    /** @return array<int, string> */
    public static function statusesCountingTowardsUploadLimit(): array
    {
        return [
            DatumStatus::Received->value,
            DatumStatus::Checking->value,
            DatumStatus::Accepted->value,
        ];
    }

    public function scopeCountsTowardsUploadLimit(Builder $query): Builder
    {
        return $query->whereIn('status', self::statusesCountingTowardsUploadLimit());
    }

    public function scopePendingAiHumanReviewFor(Builder $query, int $reviewerHemisId): Builder
    {
        return $query
            ->where('reviewer_hemis_id', $reviewerHemisId)
            ->pendingAiHumanReview();
    }

    public function scopePendingAiHumanReviews(Builder $query, int $reviewerHemisId): Builder
    {
        return $query
            ->where(function (Builder $query) use ($reviewerHemisId): void {
                $query->where('reviewer_hemis_id', $reviewerHemisId)
                    ->orWhereNull('reviewer_hemis_id');
            })
            ->pendingAiHumanReview();
    }

    public function scopePendingAiHumanReview(Builder $query): Builder
    {
        $dataTable = $query->getModel()->getTable();
        $historyTable = (new DatumHistory)->getTable();

        return $query
            ->where('status', DatumStatus::Checking->value)
            ->whereRaw(
                "COALESCE((SELECT MAX(id) FROM {$historyTable} WHERE datum_id = {$dataTable}.id AND message_type IN ('ai_evaluation', 'ai_failed', 'ai_human_review_assigned')), 0) > COALESCE((SELECT MAX(id) FROM {$historyTable} WHERE datum_id = {$dataTable}.id AND message_type IN ('submission_created', 'ai_queued', 'criterion_transferred')), 0)",
            )
            ->whereHas(
                'criterion',
                fn (Builder $query): Builder => $query->where('checking', 'ai'),
            );
    }

    public function usesAiChecking(): bool
    {
        $criterion = $this->relationLoaded('criterion')
            ? $this->criterion
            : $this->criterion()->first(['id', 'checking']);

        return $criterion?->checking === 'ai';
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_hemis_id', 'hemis_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manualScoreOption(): BelongsTo
    {
        return $this->belongsTo(CriterionManualScoreOption::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DatumHistory::class);
    }

    public function latestHistory(): HasOne
    {
        return $this->hasOne(DatumHistory::class)->latestOfMany();
    }

    public function currentFinalConfirmation(): ?DatumHistory
    {
        if ($this->status !== DatumStatus::Accepted->value) {
            return null;
        }

        $latestHistory = $this->relationLoaded('latestHistory')
            ? $this->latestHistory
            : $this->latestHistory()->with('user:id,name')->first();

        return $latestHistory?->message_type === DatumHistory::FINAL_REVIEW_CONFIRMED
            ? $latestHistory
            : null;
    }

    public function isFinalReviewConfirmed(): bool
    {
        return $this->currentFinalConfirmation() !== null;
    }

    public function resourceIdentifiers(): HasMany
    {
        return $this->hasMany(DatumResourceIdentifier::class);
    }

    /** @return Collection<int, self> */
    public function matchingSharedResourceSubmissions(): Collection
    {
        if ($this->loadMissing('criterion:id,code')->criterion?->supportsSharedResourceMatching() !== true) {
            return new Collection;
        }

        $identifiers = $this->resourceIdentifiers()
            ->whereIn('type', DatumResourceFingerprintGenerator::BLOCKING_TYPES)
            ->get(['type', 'value_hash']);

        if ($identifiers->isEmpty()) {
            return new Collection;
        }

        return self::query()
            ->select([
                'id', 'name', 'material', 'user_id', 'criterion_id', 'reviewer_hemis_id',
                'status', 'point', 'received_amount', 'author_count', 'created_at',
            ])
            ->where('id', '!=', $this->getKey())
            ->where('criterion_id', $this->criterion_id)
            ->where('user_id', '!=', $this->user_id)
            ->where('status', '!=', DatumStatus::Deleted->value)
            ->whereHas('resourceIdentifiers', function (Builder $query) use ($identifiers): void {
                $query->where(function (Builder $query) use ($identifiers): void {
                    foreach ($identifiers as $identifier) {
                        $query->orWhere(fn (Builder $query): Builder => $query
                            ->where('type', $identifier->type)
                            ->where('value_hash', $identifier->value_hash));
                    }
                });
            })
            ->with([
                'user:id,name,hemis_id',
                'latestHistory',
            ])
            ->latest()
            ->get();
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function duplicates(): HasMany
    {
        return $this->hasMany(self::class, 'duplicate_of_id');
    }

    public function storageDisk(): string
    {
        $disk = data_get($this->material, 'disk', 'public');

        return in_array($disk, ['local', 'public'], true) ? $disk : 'public';
    }

    public function storagePath(): ?string
    {
        $path = data_get($this->material, 'path');

        return data_get($this->material, 'type') === 'file' && is_string($path) ? $path : null;
    }

    public function externalUrl(): ?string
    {
        $url = data_get($this->material, 'link');

        if (data_get($this->material, 'type') !== 'url' || ! is_string($url)) {
            return null;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array($scheme, ['http', 'https'], true)
                ? $url
                : null;
    }

    /** @return array<string, int|float|string|bool> */
    public function submissionMetadata(): array
    {
        $metadata = data_get($this->material, 'article', data_get($this->material, 'data', []));

        if (! is_array($metadata)) {
            return [];
        }

        return array_filter(
            $metadata,
            static fn (mixed $value): bool => is_scalar($value) && $value !== '',
        );
    }

    /** @return array<string, array{link: string, value: int|string}> */
    public function hIndexProfiles(): array
    {
        $profiles = data_get($this->material, 'profiles', []);

        return is_array($profiles) ? $profiles : [];
    }

    protected function casts(): array
    {
        return [
            'material' => 'array',
            'point' => 'float',
            'author_count' => 'integer',
            'page_count' => 'integer',
            'impact_factor' => 'integer',
            'received_amount' => 'decimal:2',
            'reviewer_hemis_id' => 'integer',
            'duplicate_of_id' => 'integer',
            'manual_score_option_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updated(function (Datum $datum): void {
            if (! $datum->wasChanged('status')
                || in_array($datum->status, self::statusesCountingTowardsUploadLimit(), true)) {
                return;
            }

            $datum->resourceIdentifiers()->update(['active_value_hash' => null]);
        });
    }
}
