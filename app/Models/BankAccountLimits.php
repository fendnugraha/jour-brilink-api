<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccountLimits extends Model
{
    protected $guarded = ['id'];

    public function chartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }
}
