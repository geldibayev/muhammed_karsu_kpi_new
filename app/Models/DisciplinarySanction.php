<?php

namespace App\Models;

use Database\Factories\DisciplinarySanctionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplinarySanction extends Model
{
    /** @use HasFactory<DisciplinarySanctionFactory> */
    use HasFactory;

    protected $fillable = [
        'hemis_id',
        'import_id',
        'source_row',
    ];

    public function import(): BelongsTo
    {
        return $this->belongsTo(DisciplinarySanctionImport::class, 'import_id');
    }

    protected function casts(): array
    {
        return [
            'source_row' => 'integer',
        ];
    }
}
