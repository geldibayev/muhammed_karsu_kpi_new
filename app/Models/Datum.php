<?php

namespace App\Models;

use App\Enums\DatumStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Datum extends Model
{
    public const PUBLIC_CHECKING_REASON = 'Resurs bo‘yicha yakuniy qaror kutilmoqda.';

    protected $table = 'data';

    protected $fillable = [
        'id', 'name', 'material', 'user_id', 'criterion_id', 'reviewer_hemis_id', 'status', 'year_id', 'language_id',
        'point', 'author_count', 'page_count', 'impact_factor', 'publication_tier', 'reason', 'duplicate_of_id',
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
            ->where('status', DatumStatus::Checking->value)
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(DatumHistory::class);
    }

    public function resourceIdentifiers(): HasMany
    {
        return $this->hasMany(DatumResourceIdentifier::class);
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
            'reviewer_hemis_id' => 'integer',
            'duplicate_of_id' => 'integer',
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
