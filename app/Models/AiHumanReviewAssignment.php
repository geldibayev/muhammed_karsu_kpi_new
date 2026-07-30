<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiHumanReviewAssignment extends Model
{
    protected $fillable = [
        'hemis_id',
        'active_slot',
        'assigned_at',
        'ended_at',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active_slot', 1);
    }

    public static function activeHemisId(): ?int
    {
        $hemisId = static::query()->active()->value('hemis_id');

        return is_numeric($hemisId) ? (int) $hemisId : null;
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hemis_id', 'hemis_id');
    }

    protected function casts(): array
    {
        return [
            'hemis_id' => 'integer',
            'active_slot' => 'integer',
            'assigned_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }
}
