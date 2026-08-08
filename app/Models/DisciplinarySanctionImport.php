<?php

namespace App\Models;

use Database\Factories\DisciplinarySanctionImportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DisciplinarySanctionImport extends Model
{
    /** @use HasFactory<DisciplinarySanctionImportFactory> */
    use HasFactory;

    protected $fillable = [
        'source_file',
        'source_hash',
        'row_count',
        'imported_at',
    ];

    public function sanctions(): HasMany
    {
        return $this->hasMany(DisciplinarySanction::class, 'import_id');
    }

    protected function casts(): array
    {
        return [
            'row_count' => 'integer',
            'imported_at' => 'datetime',
        ];
    }
}
