<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriterionManualScoreOption extends Model
{
    protected $fillable = [
        'criterion_id',
        'code',
        'label',
        'point',
        'sort_order',
        'active',
    ];

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }

    protected function casts(): array
    {
        return [
            'label' => 'array',
            'point' => 'float',
            'active' => 'boolean',
        ];
    }
}
