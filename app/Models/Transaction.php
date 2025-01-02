<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    protected $guarded = ['id'];

    public function journal()
    {
        return $this->belongsTo(Journal::class, 'invoice', 'invoice');
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }


    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public static function generateSerialNumber($prefix, $user_id)
    {
        return time() . '-' . $prefix . '-' . $user_id . '-' . strtoupper(uniqid(rand() + time(), false));
    }

    public function payable()
    {
        return $this->belongsTo(Payable::class, 'invoice', 'invoice');
    }

    public function receivable()
    {
        return $this->belongsTo(Receivable::class, 'invoice', 'invoice');
    }
}
