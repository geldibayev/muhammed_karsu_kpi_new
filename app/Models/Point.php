<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Point extends Model
{
    protected $fillable = [
        'user_id',
        'criterion_id',
        'point',
        'report_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    protected function casts(): array
    {
        return [
            'point' => 'float',
        ];
    }
}
