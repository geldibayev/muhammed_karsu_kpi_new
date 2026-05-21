<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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

    protected $fillable = ['id', 'name', 'hemis_id', 'image', 'pos', 'rol', 'status'];

    protected $hidden = ['remember_token',];

    protected function casts(): array
    {
        return [
            'name' => 'json',
            'rol' => 'json',
        ];
    }
}
