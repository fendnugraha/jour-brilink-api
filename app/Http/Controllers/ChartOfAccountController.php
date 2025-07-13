<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Journal;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ChartOfAccountResource;
use App\Models\AccountBalance;

class ChartOfAccountController extends Controller
{
    public $startDate;
    public $endDate;
    protected $appends = ['balance'];

    /**
     * Display a listing of the resource.
     */
    public function __construct()
    {
        $this->startDate = Carbon::now()->startOfMonth();
        $this->endDate = Carbon::now()->endOfMonth();
    }

    public function index(Request $request)
    {
        $chartOfAccounts = ChartOfAccount::with(['account', 'warehouse'])
            ->when($request->search, function ($query, $search) {
                $query->where('acc_name', 'like', '%' . $search . '%')
                    ->orWhere('acc_code', 'like', '%' . $search . '%');
            })
            ->orderBy('acc_code')->paginate(10)->onEachSide(0);
        return new ChartOfAccountResource($chartOfAccounts, true, "Successfully fetched chart of accounts");
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
        $chartOfAccount = new ChartOfAccount();
        $request->validate(
            [
                'category_id' => 'required',  // Make sure category_id is present
                'name' => 'required|string|max:255|unique:chart_of_accounts,acc_name',
                'st_balance' => 'nullable|numeric',  // Allow st_balance to be nullable
            ],
            [
                'category_id.required' => 'Category account tidak boleh kosong.',
                'name.required' => 'Nama akun harus diisi.',
                'name.unique' => 'Nama akun sudah digunakan, silakan pilih nama lain.',
            ]
        );

        $chartOfAccount->create([
            'acc_code' => $chartOfAccount->acc_code($request->category_id),
            'acc_name' => $request->name,
            'account_id' => $request->category_id,
            'st_balance' => $request->st_balance ?? 0,
        ]);

        return response()->json([
            'message' => 'Chart of account created successfully',
            'chart_of_account' => $chartOfAccount
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $chartOfAccount = ChartOfAccount::with(['account', 'warehouse'])->find($id);
        return new ChartOfAccountResource($chartOfAccount, true, "Successfully fetched chart of account");
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ChartOfAccount $chartOfAccount)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request)
    {
        $chartOfAccount = ChartOfAccount::find($request->id);
        $request->validate(
            [
                'id' => 'required|exists:chart_of_accounts,id',
                'acc_name' => 'required|string|max:255|unique:chart_of_accounts,acc_name,' . $chartOfAccount->id,
                'st_balance' => 'nullable|numeric',
            ],
            [
                'acc_name.required' => 'Nama akun harus diisi.',
                'acc_name.unique' => 'Nama akun sudah digunakan, silakan pilih nama lain. ID:' . $chartOfAccount->id,
            ]
        );

        try {
            $chartOfAccount->update([
                'acc_name' => $request->acc_name,
                'st_balance' => $request->st_balance ?? 0,
            ]);

            return response()->json([
                'message' => 'Chart of account updated successfully',
                'chart_of_account' => $chartOfAccount
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to update chart of account: ' . $e->getMessage());

            return response()->json([
                'message' => 'Failed to update chart of account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        $chartOfAccount = ChartOfAccount::find($id);

        if ($chartOfAccount->is_locked) {
            return response()->json([
                'message' => 'Chart of account is locked and cannot be deleted.',
            ], 403);
        }

        if (!$chartOfAccount) {
            return response()->json([
                'message' => 'Chart of account not found.',
            ], 404); // Return a 404 error if not found
        }

        try {
            $journalExists = Journal::where('debt_code', $chartOfAccount->acc_code)
                ->orWhere('cred_code', $chartOfAccount->acc_code)
                ->exists();

            if ($journalExists) {
                return response()->json([
                    'message' => 'Chart of account cannot be deleted because it is used in a journal entry.',
                ], 400);
            }
            // Deleting the Chart of Account
            $chartOfAccount->delete();

            // Return a success response
            return response()->json([
                'message' => 'Chart of account deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to delete chart of account. ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getCashAndBankByWarehouse($warehouse)
    {
        $chartOfAccounts = ChartOfAccount::with('warehouse')->where('warehouse_id', $warehouse)->orderBy('acc_code', 'asc')->get();
        return response()->json([
            'success' => true,
            'message' => 'Successfully fetched chart of accounts',
            'data' => $chartOfAccounts
        ]);
    }

    /*************  ✨ Codeium Command ⭐  *************/
    /**
     * Delete multiple Chart of Account records.
     *
     * This function deletes the specified Chart of Account records based on the provided IDs.
     * Prior to deletion, it checks if any of the records are locked. If locked records are found,
     * it returns a response indicating that some accounts are locked and cannot be deleted.
     * Otherwise, it proceeds to delete the records and returns a success response.
     *
     * @param Request $request The HTTP request containing the IDs of the Chart of Account records to be deleted.
     * 
     * @return \Illuminate\Http\JsonResponse A JSON response indicating the result of the deletion operation.
     * If some accounts are locked, it returns a 403 status with the IDs of the locked accounts.
     * If deletion is successful, it returns a 200 status with the count of deleted records.
     */

    /******  f3f3cbc0-44ef-4107-98ae-33c5ad357b83  *******/
    public function deleteAll(Request $request)
    {
        // Retrieve the records that are about to be deleted
        $accounts = ChartOfAccount::whereIn('id', $request->ids)->get();

        // Check if any of the records are locked
        $lockedAccounts = $accounts->filter(function ($account) {
            return $account->is_locked;
        });

        if ($lockedAccounts->isNotEmpty()) {
            return response()->json(
                [
                    'message' => 'Some chart of accounts are locked and cannot be deleted.',
                    'locked_accounts' => $lockedAccounts->pluck('id'), // Optionally return the ids of locked accounts
                ],
                403
            );
        }

        // Perform the deletion if no accounts are locked
        $deletedCount = ChartOfAccount::whereIn('id', $request->ids)->delete();

        return response()->json([
            'message' => 'All chart of accounts deleted successfully',
            'deleted_count' => $deletedCount
        ], 200);
    }

    public function getCashAndBank()
    {
        $chartOfAccounts = ChartOfAccount::with('warehouse')->whereIn('account_id', [1, 2])->orderBy('acc_code', 'asc')->get();
        return new ChartOfAccountResource($chartOfAccounts, true, "Successfully fetched chart of accounts");
    }

    public function profitLossReport()
    {
        $journal = new Journal();
        // $journal->profitLossCount('0000-00-00', $endDate);

        $transactions = $journal->with(['debt', 'cred'])
            ->selectRaw('debt_code, cred_code, SUM(amount) as total')
            ->whereBetween('date_issued', [$this->startDate, $this->endDate])
            ->groupBy('debt_code', 'cred_code')
            ->get();

        $chartOfAccounts = ChartOfAccount::with(['account'])->get();

        foreach ($chartOfAccounts as $value) {
            $debit = $transactions->where('debt_code', $value->acc_code)->sum('total');
            $credit = $transactions->where('cred_code', $value->acc_code)->sum('total');

            $value->balance = ($value->account->status == "D") ? ($value->st_balance + $debit - $credit) : ($value->st_balance + $credit - $debit);
        }

        $revenue = $chartOfAccounts->whereIn('account_id', \range(27, 30))->groupBy('account_id');
        $cost = $chartOfAccounts->whereIn('account_id', \range(31, 32))->groupBy('account_id');
        $expense = $chartOfAccounts->whereIn('account_id', \range(33, 45))->groupBy('account_id');

        $profitLoss = [
            'revenue' => [
                'total' => $revenue->flatten()->sum('balance'),
                'accounts' => $revenue->map(function ($r) {
                    return [
                        'acc_name' => $r->first()->account->name,
                        'balance' => intval($r->sum('balance'))
                    ];
                })->toArray()
            ],
            'cost' => [
                'total' => $cost->flatten()->sum('balance'),
                'accounts' => $cost->map(function ($c) {
                    return [
                        'acc_name' => $c->first()->account->name,
                        'balance' => intval($c->sum('balance'))
                    ];
                })->toArray()
            ],
            'expense' => [
                'total' => $expense->flatten()->sum('balance'),
                'accounts' => $expense->map(function ($e) {
                    return [
                        'acc_name' => $e->first()->account->name,
                        'balance' => intval($e->sum('balance'))
                    ];
                })->toArray()
            ]
        ];

        return response()->json([
            'success' => true,
            'message' => 'Successfully fetched profit and loss',
            'data' => $profitLoss
        ]);
    }

    public function addCashAndBankToWarehouse($warehouse, $id)
    {
        $chartOfAccount = ChartOfAccount::find($id);

        if (!$warehouse || !$chartOfAccount) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse or chart of account not found'
            ], 404);
        }
        $updateValue = $chartOfAccount->warehouse_id ? null : $warehouse;
        $chartOfAccount->update(['warehouse_id' => $updateValue]);

        $message = $chartOfAccount->warehouse_id ? 'Cash and bank account added to warehouse' : 'Cash and bank account removed from warehouse';

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $chartOfAccount
        ]);
    }

    public function getExpenses()
    {
        $chartOfAccounts = ChartOfAccount::whereIn('account_id', range(33, 45))->get();
        return new ChartOfAccountResource($chartOfAccounts, true, "Successfully fetched chart of accounts");
    }

    public function getCashBankBalancex($warehouse, $endDate)
    {
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();
        $previousDate = Carbon::parse($endDate)->subDays()->toDateString();


        //     $accountBalances = Journal::selectRaw("
        //     chart.id as account_id,
        //     chart.acc_name as account_name,
        //     chart.st_balance,
        //     acc.status,
        //     SUM(CASE WHEN journals.debt_code = chart.id THEN journals.amount ELSE 0 END) as total_debit,
        //     SUM(CASE WHEN journals.cred_code = chart.id THEN journals.amount ELSE 0 END) as total_credit
        // ")
        //         ->join('chart_of_accounts as chart', function ($join) {
        //             $join->on('journals.debt_code', '=', 'chart.id')
        //                 ->orOn('journals.cred_code', '=', 'chart.id');
        //         })
        //         ->join('accounts as acc', 'chart.account_id', '=', 'acc.id')
        //         ->where('chart.warehouse_id', $warehouse)
        //         ->whereBetween('journals.date_issued', [Carbon::create(2010, 1, 1), $endDate])
        //         ->orderBy('chart.acc_code', 'asc')
        //         ->groupBy('chart.id', 'chart.st_balance', 'acc.status', 'chart.acc_name')
        //         ->get();


        //     foreach ($accountBalances as $acc) {

        //         $acc->balance = $acc->status === 'D'
        //             ? $acc->st_balance + $acc->total_debit - $acc->total_credit
        //             : $acc->st_balance + $acc->total_credit - $acc->total_debit;
        //     }

        $chartOfAccounts = ChartOfAccount::with('account')->where('warehouse_id', $warehouse)->get();

        foreach ($chartOfAccounts as $chartOfAccount) {

            // Mengambil saldo awal dari properti model
            $initBalance = AccountBalance::where('chart_of_account_id', $chartOfAccount->id)->where('balance_date', $previousDate)->first()?->ending_balance ?? 0; // Tambahkan null coalescing operator untuk keamanan
            // Mengambil normal balance dari relasi 'account'
            $normalBalance = $chartOfAccount->account->status ?? ''; // Tambahkan null coalescing operator

            // Menghitung total debit langsung dari database
            $debit = Journal::where('debt_code', $chartOfAccount->id)
                ->whereBetween('date_issued', [$previousDate, $endDate])
                ->sum('amount');

            // Menghitung total credit langsung dari database
            $credit = Journal::where('cred_code', $chartOfAccount->id)
                ->whereBetween('date_issued', [$previousDate, $endDate])
                ->sum('amount');

            $chartOfAccount->balance = $initBalance + ($normalBalance === 'D' ? $debit - $credit : $credit - $debit);
        }


        return new ChartOfAccountResource($chartOfAccounts, true, "Successfully fetched chart of accounts");
    }

    public function getCashBankBalance($warehouse, $endDate)
    {
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();
        $previousDate = Carbon::parse($endDate)->subDays()->toDateString();

        $chartOfAccounts = Journal::balancesByWarehouse($warehouse, $endDate);


        return new ChartOfAccountResource($chartOfAccounts, true, "Successfully fetched chart of accounts");
    }

    public function dailyDashboard(Request $request)
    {
        $warehouse = $request->query('warehouse', null);
        $startDate = $request->query('startDate') ? Carbon::parse($request->query('startDate'))->startOfDay() : Carbon::now()->startOfDay();
        $endDate = $request->query('endDate') ? Carbon::parse($request->query('endDate'))->endOfDay() : Carbon::now()->endOfDay();

        $warehouseBalance = Journal::balancesByWarehouse($warehouse, $endDate);

        $trxForSalesCount = Journal::selectRaw('
        trx_type,
        SUM(amount) as total_amount,
        SUM(fee_amount) as total_fee,
        COUNT(*) as total_count
    ')
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->when($warehouse !== 'all', fn($q) => $q->where('warehouse_id', $warehouse))
            ->groupBy('trx_type')
            ->get()
            ->keyBy('trx_type');

        $totalFee = Journal::selectRaw('
                SUM(fee_amount) as total_fee,
                SUM(CASE WHEN fee_amount > 0 THEN fee_amount ELSE 0 END) as total_fee_positive,
                SUM(CASE WHEN fee_amount < 0 THEN fee_amount ELSE 0 END) as total_fee_negative
            ')
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->when($warehouse !== 'all', fn($q) => $q->where('warehouse_id', $warehouse))
            ->first();

        $countTrxByType = Journal::whereBetween('date_issued', [$startDate, $endDate])
            ->when($warehouse !== 'all', fn($q) => $q->where('warehouse_id', $warehouse))
            ->whereIn('trx_type', ['Transfer Uang', 'Tarik Tunai', 'Deposit', 'Voucher & SP', 'Accessories'])
            ->count();


        // $dailyReport = [
        //     'totalCash' => (int) $warehouseBalance['sumtotalCash'],
        //     'totalBank' => (int) $warehouseBalance['sumtotalBank'],
        //     'totalTransfer' => (int) ($trxForSalesCount['Transfer Uang']->total_amount ?? 0),
        //     'totalCashWithdrawal' => (int) ($trxForSalesCount['Tarik Tunai']->total_amount ?? 0),
        //     'totalCashDeposit' => (int) ($trxForSalesCount['Deposit']->total_amount ?? 0),
        //     'totalVoucher' => (int) ($trxForSalesCount['Voucher & SP']->total_amount ?? 0),
        //     'totalAccessories' => (int) ($trxForSalesCount['Accessories']->total_amount ?? 0),
        //     'totalExpense' => (int) ($trxForSalesCount['Pengeluaran']->total_fee ?? 0),
        //     'totalFee' => (int) ($totalFee->total_fee_positive ?? 0),
        //     'profit' => (int) ($totalFee->total_fee ?? 0),
        //     'salesCount' => $countTrxByType
        // ];
        $dailyReport = [
            'totalCash' => (int) $warehouseBalance['sumtotalCash'],
            'totalBank' => (int) $warehouseBalance['sumtotalBank'],
            'totalTransfer' => [
                'total' => (int) ($trxForSalesCount['Transfer Uang']->total_amount ?? 0),
                'count' => (int) ($trxForSalesCount['Transfer Uang']->total_count ?? 0)
            ],
            'totalCashWithdrawal' => [
                'total' => (int) ($trxForSalesCount['Tarik Tunai']->total_amount ?? 0),
                'count' => (int) ($trxForSalesCount['Tarik Tunai']->total_count ?? 0)
            ],
            'totalCashDeposit' => [
                'total' => (int) ($trxForSalesCount['Deposit']->total_amount ?? 0),
                'count' => (int) ($trxForSalesCount['Deposit']->total_count ?? 0)
            ],
            'totalVoucher' => [
                'total' => (int) ($trxForSalesCount['Voucher & SP']->total_amount ?? 0),
                'count' => (int) ($trxForSalesCount['Voucher & SP']->total_count ?? 0)
            ],
            'totalAccessories' => [
                'total' => (int) ($trxForSalesCount['Accessories']->total_amount ?? 0),
                'count' => (int) ($trxForSalesCount['Accessories']->total_count ?? 0)
            ],
            'totalExpense' => (int) ($trxForSalesCount['Pengeluaran']->total_fee ?? 0),
            'totalFee' => (int) ($totalFee->total_fee_positive ?? 0),
            'profit' => (int) ($totalFee->total_fee ?? 0),
            'salesCount' => $countTrxByType
        ];

        return new ChartOfAccountResource($dailyReport, true, "Successfully fetched chart of accounts");
    }

    public function getAllAccounts()
    {
        $chartOfAccounts = ChartOfAccount::with(['account'])->orderBy('acc_code')->get();
        return new ChartOfAccountResource($chartOfAccounts, true, "Successfully fetched chart of accounts");
    }

    public function getAccountByAccountId(Request $request)
    {
        $accountIds = $request->input('account_ids', []);

        // Ensure it's an array
        if (!is_array($accountIds)) {
            $accountIds = explode(',', $accountIds); // Convert comma-separated values into an array
        }

        $chartOfAccounts = ChartOfAccount::with(['account'])
            ->whereIn('account_id', $accountIds)
            ->orderBy('acc_code')
            ->get();

        return new ChartOfAccountResource($chartOfAccounts, true, "Successfully fetched chart of accounts");
    }
}
