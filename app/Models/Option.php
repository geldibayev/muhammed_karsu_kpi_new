<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Option extends Model
{
    public const RESOURCE_UPLOADS_ENABLED = 'resource_uploads_enabled';

    public const AI_EVALUATIONS_ENABLED = 'ai_evaluations_enabled';

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

    public static function aiEvaluationsEnabled(): bool
    {
        $value = static::query()
            ->where('key', self::AI_EVALUATIONS_ENABLED)
            ->value('value');

        return $value === null || (string) $value !== '0';
    }

    public static function setAiEvaluationsEnabled(bool $enabled): void
    {
        static::query()->updateOrCreate(
            ['key' => self::AI_EVALUATIONS_ENABLED],
            ['value' => $enabled ? '1' : '0'],
        );
    }
}
