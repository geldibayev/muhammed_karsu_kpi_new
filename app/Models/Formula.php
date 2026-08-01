<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Formula extends Model
{
    public const Competition = 'competition';

    public const Maximum = 'maximum';

    public const Unlimited = 'unlimited';

    protected $fillable = [
        'id', 'code', 'name', 'status',
    ];

    protected function casts(): array
    {
        return [
            'name' => 'json',
        ];
    }
}
