<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriterionUploadPermission extends Model
{
    protected $fillable = [
        'user_id',
        'criterion_id',
        'granted_by_user_id',
        'reason',
        'active_key',
        'used_at',
        'datum_id',
        'revoked_at',
        'revoked_by_user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function datum(): BelongsTo
    {
        return $this->belongsTo(Datum::class);
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('active_key', true);
    }

    protected function casts(): array
    {
        return [
            'active_key' => 'boolean',
            'used_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }
}
