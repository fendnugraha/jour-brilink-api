<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Attendance extends Model
{
    protected $guarded = ['id', 'created_at', 'updated_at'];

    protected $appends = ['photo_url'];


    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($attendance) {
            if ($attendance->image) {
                $path = 'public/attendance/' . $attendance->image;

                if (Storage::exists($path)) {
                    Storage::delete($path);
                }
            }
        });
    }

    public function getPhotoUrlAttribute()
    {
        return $this->photo
            ? asset('storage/' . $this->photo)
            : null;
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}
