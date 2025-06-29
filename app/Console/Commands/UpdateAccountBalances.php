<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\Journal;
use App\Models\AccountBalance;
use App\Models\ChartOfAccount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateAccountBalances extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'accounting:update-balances {--date=} {--company=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates daily account balances in the account_balances table.';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        // Kita menggunakan kemarin agar data hari ini selesai diposting.
        $dateToUpdate = $this->option('date') ? Carbon::parse($this->option('date')) : Carbon::yesterday();

        $this->info("Updating account balances on date: {$dateToUpdate->toDateString()}...");
        Log::info("Starting account balances update for date: {$dateToUpdate->toDateString()}");

        try {
            // Mengambil semua akun (Chart of Accounts) dengan eager loading relasi 'account'
            $chartOfAccounts = ChartOfAccount::with('account')->get();

            $this->info("Found " . $chartOfAccounts->count() . " chart of accounts.");
            Log::info("Total accounts found: " . $chartOfAccounts->count());

            foreach ($chartOfAccounts as $chartOfAccount) {
                // Log untuk setiap akun yang sedang diproses
                Log::debug("Processing account: {$chartOfAccount->acc_code} ({$chartOfAccount->acc_name}) - ID: {$chartOfAccount->id}");
                $this->comment("Processing account: {$chartOfAccount->acc_code} ({$chartOfAccount->acc_name})");

                // Mengambil saldo awal dari properti model
                $initBalance = $chartOfAccount->st_balance ?? 0; // Tambahkan null coalescing operator untuk keamanan
                // Mengambil normal balance dari relasi 'account'
                $normalBalance = $chartOfAccount->account->status ?? ''; // Tambahkan null coalescing operator

                // Menghitung total debit langsung dari database
                $debit = Journal::where('debt_code', $chartOfAccount->id)
                    ->where('date_issued', '<=', $dateToUpdate->toDateString())
                    ->sum('amount');

                // Menghitung total credit langsung dari database
                $credit = Journal::where('cred_code', $chartOfAccount->id)
                    ->where('date_issued', '<=', $dateToUpdate->toDateString())
                    ->sum('amount');

                // Log nilai debit dan credit yang dihitung
                Log::debug("  Debit: {$debit}, Credit: {$credit}");

                $endingBalance = 0;
                if ($normalBalance == "D") { // Asumsi 'D' untuk Debit
                    $endingBalance = $initBalance + $debit - $credit;
                } else { // Asumsi 'C' untuk Credit
                    $endingBalance = $initBalance + $credit - $debit;
                }

                // Log saldo akhir yang dihitung
                Log::debug("  Calculated Ending Balance: {$endingBalance}");

                AccountBalance::updateOrCreate(
                    [
                        'chart_of_account_id' => $chartOfAccount->id, // Menggunakan 'account_id' sesuai migrasi 'account_balances'
                        'balance_date' => $dateToUpdate->toDateString(),
                    ],
                    [
                        'ending_balance' => $endingBalance,
                    ]
                );
                $this->comment("Account {$chartOfAccount->acc_code} ({$chartOfAccount->acc_name}): Balance updated to {$endingBalance}");
                Log::debug("Account {$chartOfAccount->acc_code} balance updated to {$endingBalance}");
            }

            $this->info("Account balances update completed for {$dateToUpdate->toDateString()}.");
            Log::info("Account balances update completed successfully for date: {$dateToUpdate->toDateString()}");
        } catch (\Exception $e) {
            $this->error("An error occurred during balance update: {$e->getMessage()}");
            Log::error("Error updating account balances for date {$dateToUpdate->toDateString()}: {$e->getMessage()}", [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
