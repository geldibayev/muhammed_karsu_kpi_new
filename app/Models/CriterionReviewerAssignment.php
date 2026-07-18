<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CriterionReviewerAssignment extends Model
{
    protected $fillable = [
        'criterion_id', 'hemis_id', 'criterion_code',
    ];

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hemis_id', 'hemis_id');
    }
}
