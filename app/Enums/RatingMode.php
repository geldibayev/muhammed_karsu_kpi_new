<?php

namespace App\Enums;

enum RatingMode: string
{
    case WithDegree = 'with_degree';
    case WithoutDegree = 'without_degree';
    case Faculties = 'faculties';
    case Departments = 'departments';

    /** @param  array<string, mixed>  $filters */
    public static function fromFilters(array $filters): self
    {
        $mode = is_string($filters['mode'] ?? null)
            ? self::tryFrom($filters['mode'])
            : null;

        if ($mode !== null) {
            return $mode;
        }

        return ($filters['degree_group'] ?? null) === self::WithoutDegree->value
            ? self::WithoutDegree
            : self::WithDegree;
    }

    public function isUnitMode(): bool
    {
        return in_array($this, [self::Faculties, self::Departments], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::WithDegree => 'Ilmiy darajaga ega',
            self::WithoutDegree => 'Ilmiy darajaga ega emas',
            self::Faculties => 'Fakultetlar bo‘yicha',
            self::Departments => 'Kafedralar bo‘yicha',
        };
    }
}
