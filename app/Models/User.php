<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    public static function make_short_name($firstname, $surname, $patronymic)
    {
        $firstname = strtoupper(trim($firstname));
        $surname = strtoupper(trim($surname));
        $patronymic = strtoupper(trim($patronymic));
        if (str_starts_with($firstname, 'SH') || str_starts_with($patronymic, 'SH')) {
            $firstInitial = 'SH';
        } else {
            $firstInitial = substr($firstname, 0, 1);
        }
        $patronymicInitial = str_starts_with($patronymic, 'SH') ? 'SH' : substr($patronymic, 0, 1);

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

    public function point($criterion_id)
    {
        $point = Point::where('user_id', auth()->id())->where('criterion_id', $criterion_id)->first();

        return $point->point ?? 0;
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
        return $this->hasRole('super_admin');
    }

    public function workplaces(): HasMany
    {
        return $this->hasMany(Workplace::class, 'user_id');
    }

    public function criterionReviewerAssignments(): HasMany
    {
        return $this->hasMany(CriterionReviewerAssignment::class, 'hemis_id', 'hemis_id');
    }
}
