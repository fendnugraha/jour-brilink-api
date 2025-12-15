<?php

namespace App\Console\Commands;

use App\Models\EmployeeWarning;
use Illuminate\Console\Command;

class ExpireEmployeeWarnings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'warnings:expire';
    protected $description = 'Auto expire employee warnings';

    public function handle()
    {
        $expired = EmployeeWarning::where('is_active', true)
            ->whereNotNull('expired_date')
            ->whereDate('expired_date', '<', now())
            ->update(['is_active' => false]);

        $this->info("Expired warnings: {$expired}");
    }
}
