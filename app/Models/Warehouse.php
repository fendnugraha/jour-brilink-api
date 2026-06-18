<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Warehouse extends Model
{
    protected $guarded = ['id'];

    public function ChartOfAccount()
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function user()
    {
        return $this->hasMany(User::class);
    }

    public function journal()
    {
        return $this->hasMany(Journal::class);
    }

    public function warehouse_expenses()
    {
        return $this->hasMany(Journal::class)->where('trx_type', 'Pengeluaran');
    }

    public function transaction()
    {
        return $this->hasMany(Transaction::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    public function attendance()
    {
        return $this->hasMany(Attendance::class);
    }

    public function zone()
    {
        return $this->belongsTo(WarehouseZone::class, 'warehouse_zone_id', 'id');
    }

    public function warehouses()
    {
        return $this->hasMany(Warehouse::class);
    }

    public static function toggleLockStatusById(int $id)
    {
        // 1. Cari data warehouse berdasarkan ID
        $warehouse = self::findOrFail($id);

        // 2. Hitung status baru
        $newStatus = $warehouse->status === 1 ? 3 : ($warehouse->status === 3 ? 1 : $warehouse->status);

        // 3. Update statusnya
        $warehouse->status = $newStatus;
        $warehouse->save();

        // 4. Kembalikan data warehouse yang sudah di-update
        return $warehouse;
    }
}
