<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Workplace extends Model
{
    protected $fillable = [
        'user_id',
        'department_id',
        'academic_degree_id',
        'academic_rank_id',
        'form_id',
        'staff_id',
        'staff_position_id',
        'status_id',
        'type_id',
    ];

    public function department(): HasOne
    {
        return $this->hasOne(Department::class, 'id', 'department_id');
    }

    public function staff(): HasOne
    {
        return $this->hasOne(EmploymentStaff::class, 'id', 'staff_id');
    }

    public function form(): HasOne
    {
        return $this->hasOne(EmploymentForm::class, 'id', 'form_id');
    }

    public function position(): HasOne
    {
        return $this->hasOne(StaffPosition::class, 'id', 'staff_position_id');
    }

    public function status(): HasOne
    {
        return $this->hasOne(EmployeeStatus::class, 'id', 'status_id');
    }

    public function academic_degree(): HasOne
    {
        return $this->hasOne(AcademicDegree::class, 'id', 'academic_degree_id');
    }

    public function academic_rank(): HasOne
    {
        return $this->hasOne(AcademicRank::class, 'id', 'academic_rank_id');
    }
}
