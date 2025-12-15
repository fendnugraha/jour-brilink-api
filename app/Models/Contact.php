<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    protected $guarded = ['id'];

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function finances()
    {
        return $this->hasMany(Finance::class);
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    public function warehouse()
    {
        return $this->hasOne(Warehouse::class, 'contact_id', 'id');
    }

    public function zone()
    {
        return $this->hasOne(WarehouseZone::class, 'employee_id', 'id');
    }

    public function employee_receivables()
    {
        return $this->hasMany(Finance::class)->where('finance_type', 'EmployeeReceivable');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'id', 'contact_id');
    }
}
