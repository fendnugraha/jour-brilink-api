<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function payroll()
    {
        return $this->hasMany(Payroll::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'contact_id', 'contact_id');
    }

    public function warnings()
    {
        return $this->hasMany(EmployeeWarning::class);
    }

    public function warningActive()
    {
        return $this->hasOne(EmployeeWarning::class)->where('is_active', true)->latestOfMany();
    }
}
