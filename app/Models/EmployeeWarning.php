<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeWarning extends Model
{
    protected $guarded = ['id', 'created_at', 'udpated_at'];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
