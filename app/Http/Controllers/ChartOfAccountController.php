<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Journal;
use App\Models\Warehouse;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\ChartOfAccountResource;

class ChartOfAccountController extends Controller
{
    public $startDate;
    public $endDate;
    /**
     * Display a listing of the resource.
     */
    public function __construct()
    {
        $this->startDate = Carbon::now()->startOfMonth();
        $this->endDate = Carbon::now()->endOfMonth();
    }

    public function index()
    {
        $chartOfAccounts = ChartOfAccount::with(['account', 'warehouse'])->orderBy('acc_code')->paginate(10);
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
}
