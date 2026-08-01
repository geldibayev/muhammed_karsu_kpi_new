<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    public const RESOURCE_UPLOADS_ENABLED = 'resource_uploads_enabled';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'key',
        'value',
    ];

    public $timestamps = false;

    public static function resourceUploadsEnabled(): bool
    {
        $value = static::query()
            ->where('key', self::RESOURCE_UPLOADS_ENABLED)
            ->value('value');

        return $value === null || (string) $value !== '0';
    }

    public static function setResourceUploadsEnabled(bool $enabled): void
    {
        static::query()->updateOrCreate(
            ['key' => self::RESOURCE_UPLOADS_ENABLED],
            ['value' => $enabled ? '1' : '0'],
        );
    }
}
