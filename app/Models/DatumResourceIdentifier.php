<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatumResourceIdentifier extends Model
{
    protected $fillable = [
        'datum_id',
        'report_id',
        'user_id',
        'type',
        'value_hash',
        'active_value_hash',
    ];

    public function datum(): BelongsTo
    {
        return $this->belongsTo(Datum::class);
    }

    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
