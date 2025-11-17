<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Department extends Model{

    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    public function clinics(){
        return $this->belongsToMany(Clinic::class, 'clinic_departments');
    }


    public function doctors(){
        return $this->hasManyThrough(
            Doctor::class,             // الموديل النهائي
            Employee::class,           // الجدول الوسيط
            'department_id',    // المفتاح الأجنبي في employees
            'employee_id',             // المفتاح الأجنبي في doctors
            'id',                      // المفتاح الأساسي في departments
            'id'                       // المفتاح الأساسي في employees
        );
    }
    public function doctors(){
    return $this->hasMany(Doctor::class);
}

// ====> هنا 
public function departmentManagers()
{
    return $this->hasMany(DepartmentManager::class);
}

public function activeManager()
{
    return $this->hasOne(DepartmentManager::class)->where('is_active', true);
}

    public function patients(){
        return $this->belongsToMany(Patient::class, 'department_patients');
    }
}
