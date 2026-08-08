<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public $incrementing = false;

    protected $keyType = 'int';

    public static function make_short_name(string $firstname, string $surname, string $patronymic): string
    {
        $firstname = mb_strtoupper(trim($firstname), 'UTF-8');
        $surname = mb_strtoupper(trim($surname), 'UTF-8');
        $patronymic = mb_strtoupper(trim($patronymic), 'UTF-8');
        $firstInitial = str_starts_with($firstname, 'SH')
            ? 'SH'
            : mb_substr($firstname, 0, 1, 'UTF-8');
        $patronymicInitial = str_starts_with($patronymic, 'SH')
            ? 'SH'
            : mb_substr($patronymic, 0, 1, 'UTF-8');

        return "$surname $firstInitial.$patronymicInitial.";
    }

    protected $fillable = ['id', 'name', 'hemis_id', 'image', 'pos', 'rol', 'status', 'degree'];

    protected $hidden = ['remember_token'];

    public function getFirstAttribute()
    {
        return $this->name['first'] ?? '';
    }

    public function getLastAttribute()
    {
        return $this->name['last'] ?? '';
    }

    public function getThirdAttribute()
    {
        return $this->name['third'] ?? '';
    }

    public function getShortAttribute()
    {
        return $this->name['short'] ?? '';
    }

    public function getFullAttribute()
    {
        return $this->name['full'] ?? '';
    }

    public function getImageUrlAttribute(): ?string
    {
        if (! is_string($this->image) || $this->image === '') {
            return null;
        }

        $images = json_decode($this->image, true);

        if (is_array($images)) {
            return data_get($images, 'min') ?: data_get($images, 'max');
        }

        return filter_var($this->image, FILTER_VALIDATE_URL) ? $this->image : null;
    }

    public function point(int $criterionId, int $reportId): float
    {
        return (float) $this->points()
            ->where('criterion_id', $criterionId)
            ->where('report_id', $reportId)
            ->value('point');
    }

    protected function casts(): array
    {
        return [
            'name' => 'json',
            'rol' => 'json',
        ];
    }

    public const ASSIGNABLE_ROLES = [
        'moder' => 'Tekshiruvchi',
        'dean' => 'Dekan',
        'department' => 'Kafedra mudiri',
        'teacher' => 'O‘qituvchi',
    ];

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->rol ?? [], true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super_admin')
            || in_array(
                (string) $this->hemis_id,
                array_map('strval', config('kpi.super_admin_hemis_ids', [])),
                true,
            );
    }

    public function ensureConfiguredSuperAdminRole(): void
    {
        if (! in_array(
            (string) $this->hemis_id,
            array_map('strval', config('kpi.super_admin_hemis_ids', [])),
            true,
        )) {
            return;
        }

        $this->rol = array_values(array_unique([
            ...($this->rol ?? []),
            'super_admin',
            'teacher',
        ]));
    }

    public function isActive(): bool
    {
        return $this->status === '1';
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '1');
    }

    public function scopeAcademicRatingParticipants(Builder $query): Builder
    {
        return $query->whereHas(
            'ratingWorkplace.department',
            fn (Builder $departmentQuery): Builder => $departmentQuery->academicRatingUnits(),
        );
    }

    public function workplaces(): HasMany
    {
        return $this->hasMany(Workplace::class, 'user_id');
    }

    public function primaryWorkplaces(): HasMany
    {
        return $this->workplaces()
            ->where('form_id', EmploymentForm::PRIMARY_WORKPLACE_ID);
    }

    public function primaryWorkplace(): HasOne
    {
        return $this->hasOne(Workplace::class)
            ->ofMany(
                ['id' => 'min'],
                fn (Builder $query): Builder => $query
                    ->where('form_id', EmploymentForm::PRIMARY_WORKPLACE_ID),
            );
    }

    public function ratingWorkplace(): HasOne
    {
        return $this->hasOne(Workplace::class)
            ->ofMany([
                'form_id' => 'min',
                'id' => 'min',
            ]);
    }

    public function points(): HasMany
    {
        return $this->hasMany(Point::class);
    }

    public function criterionPoints(): HasMany
    {
        return $this->hasMany(CriterionPoint::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Datum::class);
    }

    public function criterionReviewerAssignments(): HasMany
    {
        return $this->hasMany(CriterionReviewerAssignment::class, 'hemis_id', 'hemis_id');
    }
}
