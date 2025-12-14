<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    protected $guarded = ['id'];

    public function payroll()
    {
        return $this->belongsTo(Payroll::class);
    }
}
