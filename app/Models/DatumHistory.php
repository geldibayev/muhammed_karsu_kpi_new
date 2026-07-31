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

    public function isVisibleToSubmitter(): bool
    {
        return ! in_array($this->message_type, [
            'ai_human_review_assigned',
            'ai_human_review_unassigned',
        ], true);
    }

    public function messageForSubmitter(): ?string
    {
        if (! $this->isVisibleToSubmitter()) {
            return null;
        }

        if ($this->message_type === 'ai_failed'
            || ($this->message_type === 'ai_evaluation' && $this->type === 'warning')) {
            return Datum::PUBLIC_CHECKING_REASON;
        }

        return $this->message;
    }
}
