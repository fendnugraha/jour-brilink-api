<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Correction extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    public function journal()
    {
        return $this->belongsTo(Journal::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
