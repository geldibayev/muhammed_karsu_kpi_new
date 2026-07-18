<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(EmploymentStaff::class);
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(EmploymentForm::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(StaffPosition::class, 'staff_position_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(EmployeeStatus::class, 'status_id');
    }

    public function academic_degree(): BelongsTo
    {
        return $this->belongsTo(AcademicDegree::class);
    }

    public function academic_rank(): BelongsTo
    {
        return $this->belongsTo(AcademicRank::class);
    }
}
