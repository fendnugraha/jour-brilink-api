<?php

namespace App\Jobs;

use App\Models\Warehouse;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWarehouseLock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $warehouseId;
    protected int $newStatus;

    // Ambil data parameter dari controller saat dipicu
    public function __construct(int $warehouseId, int $newStatus)
    {
        $this->warehouseId = $warehouseId;
        $this->newStatus = $newStatus;
    }

    // Eksekusi penguncian sesungguhnya di latar belakang setelah waktu delay habis
    public function handle()
    {
        $warehouse = Warehouse::find($this->warehouseId);

        if ($warehouse && $warehouse->id != 1) {
            $warehouse->status = $this->newStatus;
            $warehouse->save();
        }
    }
}
