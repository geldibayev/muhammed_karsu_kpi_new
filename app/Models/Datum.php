<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Datum extends Model
{
    protected $table = 'data';

    protected $fillable = [
        'id', 'name', 'material', 'user_id', 'criterion_id', 'status', 'year_id', 'language_id', 'point', 'reason',
    ];

    protected $casts = [
        'material' => 'json',
    ];

    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criterion::class);
    }
}
