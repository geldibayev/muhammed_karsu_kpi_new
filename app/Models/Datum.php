<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Datum extends Model
{
    protected $table = 'data';

    protected $fillable = [
        'id', 'name', 'material', 'user_id', 'criterion_id', 'status', 'year_id', 'language_id', 'point', 'reason',
    ];

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

    protected function casts(): array
    {
        return [
            'material' => 'array',
            'point' => 'float',
        ];
    }
}
