<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Journal;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\AccountResource;
use App\Models\AccountBalance;
use App\Models\LogActivity;

class JournalController extends Controller
{
    public $startDate;
    public $endDate;
    /**
     * Display a listing of the resource.
     */

    public function __construct()
    {
        $this->startDate = Carbon::now()->startOfDay();
        $this->endDate = Carbon::now()->endOfDay();
    }

    public function index()
    {
        $journals = Journal::with(['debt', 'cred'])->orderBy('created_at', 'desc')->paginate(10, ['*'], 'journalPage')->onEachSide(0)->withQueryString();
        return new AccountResource($journals, true, "Successfully fetched journals");
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $journal = Journal::with(['debt', 'cred'])->find($id);
        return new AccountResource($journal, true, "Successfully fetched journal");
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'cred_code' => 'required|exists:chart_of_accounts,id',
            'debt_code' => 'required|exists:chart_of_accounts,id',
            'amount' => 'required|numeric|min:0',
            'fee_amount' => 'required|numeric|min:0',
            'description' => 'max:255',
        ]);

        $journal = Journal::findOrFail($id); // Better to fail gracefully
        $log = new LogActivity();
        $isAmountChanged = $journal->amount != $request->amount;
        $isFeeAmountChanged = $journal->fee_amount != $request->fee_amount;

        DB::beginTransaction();
        try {
            $oldAmount = $journal->amount;
            $oldFeeAmount = $journal->fee_amount;

            $journal->update($request->all());

            $descriptionParts = [];
            if ($isAmountChanged) {
                $oldAmountFormatted = number_format($oldAmount, 0, ',', '.');
                $newAmountFormatted = number_format($request->amount, 0, ',', '.');
                $descriptionParts[] = "Amount changed from Rp $oldAmountFormatted to Rp $newAmountFormatted.";
            }
            if ($isFeeAmountChanged) {
                $oldFeeFormatted = number_format($oldFeeAmount, 0, ',', '.');
                $newFeeFormatted = number_format($request->fee_amount, 0, ',', '.');
                $descriptionParts[] = "Fee amount changed from Rp $oldFeeFormatted to Rp $newFeeFormatted.";
            }


            if ($isAmountChanged || $isFeeAmountChanged) {
                $log->create([
                    'user_id' => auth()->id(),
                    'warehouse_id' => $journal->warehouse_id,
                    'activity' => 'Updated Journal',
                    'description' => 'Updated Journal with ID: ' . $journal->id . '. ' . implode(' ', $descriptionParts),
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update journal',
            ]);
        }

        return new AccountResource($journal, true, "Successfully updated journal");
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Journal $journal)
    {
        $transactionsExist = $journal->transaction()->exists();
        // if ($transactionsExist) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Journal cannot be deleted because it has transactions'
        //     ]);
        // }
        $log = new LogActivity();
        DB::beginTransaction();
        try {
            $journal->delete();
            if ($transactionsExist) {
                $journal->transaction()->delete();
            }

            $log->create([
                'user_id' => auth()->user()->id,
                'warehouse_id' => $journal->warehouse_id,
                'activity' => 'Deleted Journal',
                'description' => 'Deleted Journal with ID: ' . $journal->id . ' (' . $journal->description . ' from ' . $journal->cred->acc_name . ' to ' . $journal->debt->acc_name . ' with amount: ' . number_format($journal->amount, 0, ',', '.') . ' and fee amount: ' . number_format($journal->fee_amount, 0, ',', '.') . ')',
            ]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Journal deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            // Flash an error message
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete journal'
            ]);
        }
    }

    public function createTransfer(Request $request)
    {
        $request->validate([
            'debt_code' => 'required|exists:chart_of_accounts,id',
            'cred_code' => 'required|exists:chart_of_accounts,id',
            'amount' => 'required|numeric|min:0',
            'trx_type' => 'required',
            'fee_amount' => 'required|numeric|min:0',
            'custName' => 'required|regex:/^[a-zA-Z0-9\s]+$/|min:3|max:255',
        ], [
            'debt_code.required' => 'Akun debet harus diisi.',
            'cred_code.required' => 'Akun kredit harus diisi.',
            'custName.required' => 'Customer name harus diisi.',
            'custName.regex' => 'Customer name tidak valid.',
        ]);
        $description = $request->description ? $request->description . ' - ' . strtoupper($request->custName) : $request->trx_type . ' - ' . strtoupper($request->custName);

        DB::beginTransaction();
        try {
            $journal = Journal::create([
                'invoice' => Journal::invoice_journal(),  // Menggunakan metode statis untuk invoice
                'date_issued' => now(),
                'debt_code' => $request->debt_code,
                'cred_code' => $request->cred_code,
                'amount' => $request->amount,
                'fee_amount' => $request->fee_amount,
                'trx_type' => $request->trx_type,
                'description' => $description,
                'user_id' => auth()->user()->id,
                'warehouse_id' => auth()->user()->role->warehouse_id
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Transaksi berhasil',
                'journal' => $journal->load('debt', 'cred')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create journal'
            ], 500);
        }
    }

    public function createVoucher(Request $request)
    {
        $request->validate([
            'qty' => 'required|numeric',
            'price' => 'required|numeric',
            'product_id' => 'required',
        ], [
            'qty.required' => 'Jumlah voucher harus diisi.',
            'qty.numeric' => 'Jumlah voucher harus berupa angka.',
            'price.required' => 'Harga voucher harus diisi.',
            'price.numeric' => 'Harga voucher harus berupa angka.',
            'product_id.required' => 'Pilih produk terlebih dahulu.',
        ]);

        $journal = new Journal();
        // $modal = $this->modal * $this->qty;
        $price = $request->price * $request->qty;
        $cost = Product::find($request->product_id)->cost;
        $modal = $cost * $request->qty;

        $description = $request->description ?? "Penjualan Voucher & SP";
        $fee = $price - $modal;
        $invoice = $journal->invoice_journal();

        DB::beginTransaction();
        try {
            $journal->create([
                'invoice' => $invoice,  // Menggunakan metode statis untuk invoice
                'date_issued' => now(),
                'debt_code' => 9,
                'cred_code' => 9,
                'amount' => $modal,
                'fee_amount' => $fee,
                'trx_type' => 'Voucher & SP',
                'description' => $description,
                'user_id' => auth()->user()->id,
                'warehouse_id' => auth()->user()->role->warehouse_id
            ]);

            $sale = new Transaction([
                'date_issued' => now(),
                'invoice' => $invoice,
                'product_id' => $request->product_id,
                'quantity' => -$request->qty,
                'price' => $request->price,
                'cost' => $cost,
                'transaction_type' => 'Sales',
                'contact_id' => 1,
                'warehouse_id' => auth()->user()->role->warehouse_id,
                'user_id' => auth()->user()->id
            ]);
            $sale->save();

            $sold = Product::find($request->product_id)->sold + $request->qty;
            Product::find($request->product_id)->update(['sold' => $sold]);

            DB::commit();

            return response()->json([
                'message' => 'Penjualan voucher berhasil, invoice: ' . $invoice,
                'journal' => $journal
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create journal'
            ], 500);
        }
    }

    public function createDeposit(Request $request)
    {
        $journal = new Journal();
        $request->validate([
            'cost' => 'required|numeric',
            'price' => 'required|numeric',
        ], [
            'cost.required' => 'Biaya deposit harus diisi.',
            'cost.numeric' => 'Biaya deposit harus berupa angka.',
            'price.required' => 'Harga deposit harus diisi.',
            'price.numeric' => 'Harga deposit harus berupa angka.',
        ]);

        // $modal = $request->modal * $request->qty;
        $price = $request->price;
        $cost = $request->cost;

        $description = $request->description ?? "Penjualan Pulsa Dll";
        $fee = $price - $cost;
        $invoice = Journal::invoice_journal();

        DB::beginTransaction();
        try {
            $journal->create([
                'invoice' => $invoice,  // Menggunakan metode statis untuk invoice
                'date_issued' => now(),
                'debt_code' => 9,
                'cred_code' => 9,
                'amount' => $cost,
                'fee_amount' => $fee,
                'trx_type' => 'Deposit',
                'description' => $description,
                'user_id' => auth()->user()->id,
                'warehouse_id' => auth()->user()->role->warehouse_id
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Penjualan deposit berhasil, invoice: ' . $invoice,
                'journal' => $journal
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create journal'
            ], 500);
        }
    }

    public function createMutation(Request $request)
    {
        $request->validate([
            'debt_code' => 'required|exists:chart_of_accounts,id',
            'cred_code' => 'required|exists:chart_of_accounts,id',
            'amount' => 'required|numeric',
            'trx_type' => 'required',
            'admin_fee' => 'numeric|min:0',
        ], [
            'admin_fee.numeric' => 'Biaya admin harus berupa angka.',
            'debt_code.required' => 'Akun debet harus diisi.',
            'cred_code.required' => 'Akun kredit harus diisi.',
        ]);

        $description = $request->description ?? 'Mutasi Kas';
        $hqCashAccount = Warehouse::find(1)->chart_of_account_id;
        DB::beginTransaction();
        try {
            $journal = Journal::create([
                'invoice' => Journal::invoice_journal(),  // Menggunakan metode statis untuk invoice
                'date_issued' => now(),
                'debt_code' => $request->debt_code,
                'cred_code' => $request->cred_code,
                'amount' => $request->amount,
                'fee_amount' => $request->fee_amount,
                'trx_type' => $request->trx_type,
                'description' => $description,
                'user_id' => auth()->user()->id,
                'warehouse_id' => auth()->user()->role->warehouse_id
            ]);

            if ($request->admin_fee > 0) {
                Journal::create([
                    'invoice' => Journal::invoice_journal(),  // Menggunakan metode statis untuk invoice
                    'date_issued' => now(),
                    'debt_code' => $hqCashAccount,
                    'cred_code' => $request->cred_code,
                    'amount' => $request->admin_fee,
                    'fee_amount' => -$request->admin_fee,
                    'trx_type' => 'Pengeluaran',
                    'description' => $description ?? 'Biaya admin Mutasi Saldo Kas',
                    'user_id' => auth()->user()->id,
                    'warehouse_id' => 1
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Mutasi Kas berhasil',
                'journal' => $journal->load(['debt', 'cred'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create journal'
            ], 500);
        }
    }

    public function getJournalByWarehouse($warehouse, $startDate, $endDate)
    {
        $chartOfAccounts = ChartOfAccount::where('warehouse_id', $warehouse)->pluck('id')->toArray();
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfDay();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $journals = Journal::with(['debt', 'cred', 'transaction.product'])
            ->where(function ($query) use ($chartOfAccounts, $startDate, $endDate) {
                // Filter based on chart of accounts (either debt_code or cred_code)
                $query->where(function ($subQuery) use ($chartOfAccounts) {
                    $subQuery->whereIn('debt_code', $chartOfAccounts)
                        ->orWhereIn('cred_code', $chartOfAccounts);
                })
                    ->whereBetween('date_issued', [$startDate, $endDate]);
            })
            ->orWhere(function ($query) use ($warehouse, $startDate, $endDate) {
                // Ensure that either debt_code or cred_code is 9, and warehouse_id is as specified
                $query->where(function ($subQuery) {
                    $subQuery->where('debt_code', 9)
                        ->orWhere('cred_code', 9);
                })
                    ->where('warehouse_id', $warehouse)
                    ->whereBetween('date_issued', [$startDate, $endDate]); // Apply whereBetween here as well
            })
            ->orderBy('created_at', 'desc')
            ->get();



        return new AccountResource($journals, true, "Successfully fetched journals");
    }

    public function getExpenses($warehouse, $startDate, $endDate)
    {
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfDay();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $expenses = Journal::with('warehouse', 'debt')
            ->where(function ($query) use ($warehouse) {
                if ($warehouse === "all") {
                    $query;
                } else {
                    $query->where('warehouse_id', $warehouse);
                }
            })
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->where('trx_type', 'Pengeluaran')
            ->orderBy('id', 'desc')
            ->get();
        return new AccountResource($expenses, true, "Successfully fetched chart of accounts");
    }

    public function getWarehouseBalance($endDate)
    {
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();
        $previousDate = $endDate->copy()->subDay()->toDateString(); // Tanggal untuk mencari saldo awal

        // --- Perbaikan Kinerja: Pre-fetch data jurnal dan saldo sebelumnya dalam satu/dua kueri ---

        // 1. Ambil semua ChartOfAccount yang relevan
        $chartOfAccounts = ChartOfAccount::with('account')->get();

        Log::info("Found " . $chartOfAccounts->count() . " chart of accounts.");

        // Dapatkan semua ID akun untuk kueri berikutnya
        $allAccountIds = $chartOfAccounts->pluck('id')->toArray();

        // 2. Pre-fetch saldo akhir hari sebelumnya untuk SEMUA akun yang relevan
        // Menggunakan array asosiatif [chart_of_account_id => ending_balance] untuk look-up cepat
        $previousDayBalances = AccountBalance::whereIn('chart_of_account_id', $allAccountIds)
            ->where('balance_date', $previousDate)
            ->pluck('ending_balance', 'chart_of_account_id')
            ->toArray();
        Log::info("Fetched " . count($previousDayBalances) . " previous day balances for {$previousDate}.");


        // 3. Pre-fetch total debit aktivitas untuk HANYA tanggal $endDate
        $dailyDebits = Journal::selectRaw('debt_code as account_id, SUM(amount) as total_amount')
            ->whereIn('debt_code', $allAccountIds)
            ->whereBetween('date_issued', [$previousDate, $endDate]) // HANYA AKTIVITAS HARI INI
            ->groupBy('debt_code')
            ->pluck('total_amount', 'account_id')
            ->toArray();
        Log::info("Fetched " . count($dailyDebits) . " daily debit sums for {$endDate->toDateString()}.");


        // 4. Pre-fetch total credit aktivitas untuk HANYA tanggal $endDate
        $dailyCredits = Journal::selectRaw('cred_code as account_id, SUM(amount) as total_amount')
            ->whereIn('cred_code', $allAccountIds)
            ->whereBetween('date_issued', [$previousDate, $endDate]) // HANYA AKTIVITAS HARI INI
            ->groupBy('cred_code')
            ->pluck('total_amount', 'account_id')
            ->toArray();
        Log::info("Fetched " . count($dailyCredits) . " daily credit sums for {$endDate->toDateString()}.");


        // --- Logic untuk memeriksa dan memicu update saldo yang hilang ---
        $missingDatesToUpdate = [];
        foreach ($allAccountIds as $accountId) {
            // Memeriksa keberadaan saldo menggunakan chart_of_account_id
            if (!isset($previousDayBalances[$accountId])) {
                // Jika saldo hari sebelumnya tidak ditemukan di account_balances
                $missingDatesToUpdate[$previousDate] = true; // Tambahkan tanggal ini ke daftar
                Log::warning("Missing AccountBalance record for account ID {$accountId} on {$previousDate}. Will trigger update.");
            }
        }

        foreach (array_keys($missingDatesToUpdate) as $date) {
            Log::info("Calling _updateBalancesDirectly for missing date: {$date}");
            // Memanggil fungsi baru secara langsung di controller
            $this->_updateBalancesDirectly($date);
        }
        // --- Akhir logic pemicu ---

        // --- PENTING: Re-fetch previousDayBalances setelah _updateBalancesDirectly dipanggil ---
        // Ini memastikan bahwa jika _updateBalancesDirectly baru saja menambahkan data,
        // data tersebut akan tersedia untuk perhitungan saldo selanjutnya dalam permintaan ini.
        if (!empty($missingDatesToUpdate)) {
            Log::info("Re-fetching previous day balances after direct updates.");
            $previousDayBalances = AccountBalance::whereIn('chart_of_account_id', $allAccountIds)
                ->where('balance_date', $previousDate)
                ->pluck('ending_balance', 'chart_of_account_id')
                ->toArray();
            Log::info("Re-fetched " . count($previousDayBalances) . " previous day balances.");
        }


        // --- Perhitungan Saldo per Akun ---
        foreach ($chartOfAccounts as $chartOfAccount) {
            // Mengambil saldo awal dari previousDayBalances atau fallback ke st_balance
            // Menggunakan chart_of_account_id untuk look-up di previousDayBalances
            $initBalance = $previousDayBalances[$chartOfAccount->id] ?? ($chartOfAccount->st_balance ?? 0.00);
            $normalBalance = $chartOfAccount->account->status ?? '';

            // Mengambil debit/credit hari ini dari pre-fetched arrays
            $debitToday = $dailyDebits[$chartOfAccount->id] ?? 0.00;
            $creditToday = $dailyCredits[$chartOfAccount->id] ?? 0.00;

            // Hitung saldo akhir
            $chartOfAccount->balance = $initBalance + ($normalBalance === 'D' ? $debitToday - $creditToday : $creditToday - $debitToday);
        }

        // --- Filter cash/bank accounts ---
        // Filter di sini harus menggunakan relasi 'account' karena acc_id ada di sana
        $sumtotalCash = $chartOfAccounts->filter(function ($coa) {
            return ($coa->account && $coa->account->id === 1); // Asumsi acc_id 1 untuk Cash
        });
        $sumtotalBank = $chartOfAccounts->filter(function ($coa) {
            return ($coa->account && $coa->account->id === 2); // Asumsi acc_id 2 untuk Bank
        });


        // Ambil warehouse
        $warehouses = Warehouse::where('status', 1)->orderBy('name')->get();

        $data = [
            'warehouse' => $warehouses->map(function ($w) use ($chartOfAccounts) {
                return [
                    'id' => $w->id,
                    'name' => $w->name,
                    // Filter di sini juga harus menggunakan relasi 'account'
                    'cash' => $chartOfAccounts->filter(function ($coa) use ($w) {
                        return ($coa->account && $coa->account->id === 1 && $coa->warehouse_id === $w->id);
                    })->sum('balance'),
                    'bank' => $chartOfAccounts->filter(function ($coa) use ($w) {
                        return ($coa->account && $coa->account->id === 2 && $coa->warehouse_id === $w->id);
                    })->sum('balance'),
                ];
            }),
            'totalCash' => $sumtotalCash->sum('balance'),
            'totalBank' => $sumtotalBank->sum('balance'),
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }

    /**
     * Menghitung dan memperbarui saldo akun di tabel account_balances untuk tanggal tertentu.
     * Ini adalah replika logika dari Artisan Command UpdateAccountBalances,
     * dieksekusi secara langsung dalam controller.
     *
     * @param string $dateToUpdate Tanggal untuk memperbarui saldo (YYYY-MM-DD).
     * @return void
     */
    protected function _updateBalancesDirectly(string $dateToUpdate): void
    {
        // Parsing tanggal untuk memastikan format yang benar
        $targetDate = Carbon::parse($dateToUpdate);

        Log::info("Directly updating account balances for date: {$targetDate->toDateString()}...");

        try {
            $chartOfAccounts = ChartOfAccount::all();

            Log::info("Total accounts found for direct update: " . $chartOfAccounts->count());

            foreach ($chartOfAccounts as $chartOfAccount) {
                // Mengambil saldo awal dari properti model chartOfAccount->st_balance
                // Ini adalah saldo kumulatif dari awal waktu hingga hari sebelumnya
                $initBalance = $chartOfAccount->st_balance ?? 0.00;

                // Menghitung total debit langsung dari database hingga targetDate
                $totalDebit = Journal::where('debt_code', $chartOfAccount->id)
                    ->where('date_issued', '<=', $targetDate->toDateString())
                    ->sum('amount');

                // Menghitung total credit langsung dari database hingga targetDate
                $totalCredit = Journal::where('cred_code', $chartOfAccount->id)
                    ->where('date_issued', '<=', $targetDate->toDateString())
                    ->sum('amount');

                // Mengambil normal balance dari relasi 'account'
                $normalBalance = $chartOfAccount->account->status ?? '';

                $endingBalance = 0;
                if ($normalBalance === 'D') { // Asumsi 'D' untuk Debit
                    $endingBalance = $initBalance + $totalDebit - $totalCredit;
                } else { // Asumsi 'C' untuk Credit
                    $endingBalance = $initBalance + $totalCredit - $totalDebit;
                }

                // Simpan atau perbarui saldo di tabel account_balances
                AccountBalance::updateOrCreate(
                    [
                        'chart_of_account_id' => $chartOfAccount->id,
                        'balance_date' => $targetDate->toDateString(),
                    ],
                    [
                        'ending_balance' => $endingBalance,
                    ]
                );
                Log::debug("Direct update: Account {$chartOfAccount->acc_code} ({$chartOfAccount->acc_name}): Balance updated to {$endingBalance} for {$targetDate->toDateString()}");
            }

            Log::info("Direct account balances update completed for {$targetDate->toDateString()}.");
        } catch (\Exception $e) {
            Log::error("Error during direct balance update for date {$targetDate->toDateString()}: {$e->getMessage()}", [
                'exception' => $e,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function getRevenueReport($startDate, $endDate)
    {
        $journal = new Journal();
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfDay();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $revenue = $journal->with(['warehouse'])
            ->selectRaw('SUM(amount) as total, warehouse_id, SUM(fee_amount) + 0 as sumfee')
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->groupBy('warehouse_id')
            ->orderBy('sumfee', 'desc')
            ->get();

        $data = [
            'revenue' => $revenue->map(function ($r) use ($startDate, $endDate) {
                $rv = $r->whereBetween('date_issued', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay()
                ])
                    ->where('trx_type', '!=', 'Mutasi Kas')
                    ->where('trx_type', '!=', 'Jurnal Umum')
                    ->where('warehouse_id', $r->warehouse_id)->get();
                return [
                    'warehouse' => $r->warehouse->name,
                    'warehouseId' => $r->warehouse_id,
                    'warehouse_code' => $r->warehouse->code,
                    'transfer' => $rv->where('trx_type', 'Transfer Uang')->sum('amount'),
                    'tarikTunai' => $rv->where('trx_type', 'Tarik Tunai')->sum('amount'),
                    'voucher' => $rv->where('trx_type', 'Voucher & SP')->sum('amount'),
                    'accessories' => $rv->where('trx_type', 'Accessories')->sum('amount'),
                    'deposit' => $rv->where('trx_type', 'Deposit')->sum('amount'),
                    'trx' => $rv->count() - $rv->where('trx_type', 'Pengeluaran')->count(),
                    'expense' => -$rv->where('trx_type', 'Pengeluaran')->sum('fee_amount'),
                    'fee' => doubleval($r->sumfee ?? 0)
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }

    public function getRevenueReportByWarehouse($warehouseId, $month, $year)
    {
        $startDate = Carbon::parse("$year-$month-01")->startOfMonth();
        $endDate = Carbon::parse("$year-$month-01")->endOfMonth();

        $journal = new Journal();

        // Data harian
        $revenue = $journal->selectRaw("
            DATE(date_issued) as date,
            SUM(CASE WHEN trx_type = 'Transfer Uang' THEN amount ELSE 0 END) as transfer,
            SUM(CASE WHEN trx_type = 'Tarik Tunai' THEN amount ELSE 0 END) as tarikTunai,
            SUM(CASE WHEN trx_type = 'Voucher & SP' THEN amount ELSE 0 END) as voucher,
            SUM(CASE WHEN trx_type = 'Deposit' THEN amount ELSE 0 END) as deposit,
            COUNT(*) - COUNT(CASE WHEN trx_type = 'Pengeluaran' THEN 1 ELSE NULL END) as trx,
            -SUM(CASE WHEN trx_type = 'Pengeluaran' THEN fee_amount ELSE 0 END) as expense,
            SUM(fee_amount) as fee
        ")
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->where('warehouse_id', $warehouseId)
            ->whereNotIn('trx_type', ['Mutasi Kas', 'Jurnal Umum'])
            ->groupBy('date')
            ->get();

        // Total keseluruhan
        $totals = $journal->selectRaw("
            SUM(CASE WHEN trx_type = 'Transfer Uang' THEN amount ELSE 0 END) as totalTransfer,
            SUM(CASE WHEN trx_type = 'Tarik Tunai' THEN amount ELSE 0 END) as totalTarikTunai,
            SUM(CASE WHEN trx_type = 'Voucher & SP' THEN amount ELSE 0 END) as totalVoucher,
            SUM(CASE WHEN trx_type = 'Deposit' THEN amount ELSE 0 END) as totalDeposit,
            COUNT(*) - COUNT(CASE WHEN trx_type = 'Pengeluaran' THEN 1 ELSE NULL END) as totalTrx,
            -SUM(CASE WHEN trx_type = 'Pengeluaran' THEN fee_amount ELSE 0 END) as totalExpense,
            SUM(fee_amount) as totalFee
        ")
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->where('warehouse_id', $warehouseId)
            ->whereNotIn('trx_type', ['Mutasi Kas', 'Jurnal Umum'])
            ->first();

        return response()->json([
            'success' => true,
            'data' => [
                'revenue' => $revenue,
                'totals' => $totals
            ]
        ], 200);
    }

    public function mutationHistory($account, $startDate, $endDate, Request $request)
    {
        $journal = new Journal();
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfDay();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $journal = new Journal();
        $journals = $journal->with('debt.account', 'cred.account', 'warehouse', 'user')
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->where(function ($query) use ($request) {
                $query->where('invoice', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('amount', 'like', '%' . $request->search . '%');
            })
            ->where(function ($query) use ($account) {
                $query->where('debt_code', $account)
                    ->orWhere('cred_code', $account);
            })
            ->orderBy('date_issued', 'asc')
            ->paginate(10, ['*'], 'mutationHistory');

        $total = $journal->with('debt.account', 'cred.account', 'warehouse', 'user')->where('debt_code', $account)
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->orWhere('cred_code', $account)
            ->WhereBetween('date_issued', [$startDate, $endDate])
            ->orderBy('date_issued', 'asc')
            ->get();

        $initBalanceDate = Carbon::parse($startDate)->subDay(1)->endOfDay();

        $debt_total = $total->where('debt_code', $account)->sum('amount');
        $cred_total = $total->where('cred_code', $account)->sum('amount');

        $data = [
            'journals' => $journals,
            'initBalance' => $journal->endBalanceBetweenDate($account, '0000-00-00', $initBalanceDate),
            'endBalance' => $journal->endBalanceBetweenDate($account, '0000-00-00', $endDate),
            'debt_total' => $debt_total,
            'cred_total' => $cred_total,
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }

    public function getRankByProfit()
    {
        $journal = new Journal();
        $startDate = Carbon::now()->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $revenue = $journal->with('warehouse')->selectRaw('SUM(fee_amount) as total, warehouse_id')
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->where('warehouse_id', '!=', 1)
            ->groupBy('warehouse_id')
            ->orderBy('total', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $revenue
        ], 200);
    }
}
