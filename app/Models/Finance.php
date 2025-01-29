<?php

namespace App\Models;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

class Finance extends Model
{
    protected $guarded = ['id'];

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function invoice_finance($contact_id)
    {
        $lastInvoice = DB::table('finances')
            ->select(DB::raw('MAX(RIGHT(invoice,7)) AS kd_max'))
            ->where([
                ['contact_id', $contact_id],
            ])
            ->whereDate('created_at', date('Y-m-d'))
            ->get();

        $kd = "";
        if ($lastInvoice[0]->kd_max != null) {
            $tmp = ((int)$lastInvoice[0]->kd_max) + 1;
            $kd = sprintf("%07s", $tmp);
        } else {
            $kd = "0000001";
        }

        return 'PY.BK.' . date('dmY') . '.' . $contact_id . '.' . $kd;
    }
}
