<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DatumHistory extends Model
{
    protected $fillable = [
        'datum_id', 'user_id', 'type', 'message', 'message_type',
    ];

    public function datum(): BelongsTo
    {
        return $this->belongsTo(Datum::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
