<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class ChartOfAccount extends Model
{
    const SAVING_ACCOUNT = 160;
    protected $guarded = ['id'];

    protected $casts = [
        'acc_code' => 'string',
        'acc_name' => 'string',
        'account_id' => 'integer',
        'warehouse_id' => 'integer',
        'st_balance' => 'integer',
    ];

    public function debt()
    {
        return $this->hasMany(Journal::class, 'debt_code', 'id');
    }

    public function cred()
    {
        return $this->hasMany(Journal::class, 'cred_code', 'id');
    }

    public function acc_code($account_id)
    {
        $accounts = Account::find($account_id);

        $lastCode = DB::table('chart_of_accounts')
            ->select(DB::raw('MAX(RIGHT(acc_code,3)) AS lastCode'))
            ->where('account_id', $account_id)
            ->get();

        if ($lastCode[0]->lastCode != null) {
            $kd = $lastCode[0]->lastCode + 1;
        } else {
            $kd = "001";
        }

        return $accounts->code . '-' . \sprintf("%03s", $kd);
    }
    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }
}
