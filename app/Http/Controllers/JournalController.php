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

        $journal = Journal::find($id);
        $journal->update($request->all());
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
        DB::beginTransaction();
        try {
            $journal->delete();
            if ($transactionsExist) {
                $journal->transaction()->delete();
            }
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
        $journal = new Journal();
        $request->validate([
            'debt_code' => 'required|exists:chart_of_accounts,id',
            'cred_code' => 'required|exists:chart_of_accounts,id',
            'amount' => 'required|numeric|min:0',
            'trx_type' => 'required',
            'fee_amount' => 'required|numeric|min:0',
            'custName' => 'required|regex:/^[a-zA-Z0-9\s]+$/|min:3|max:255',
        ]);
        $description = $request->description ? $request->description . ' - ' . strtoupper($request->custName) : $request->trx_type . ' - ' . strtoupper($request->custName);

        DB::beginTransaction();
        try {
            $journal->create([
                'invoice' => $journal->invoice_journal(),  // Menggunakan metode statis untuk invoice
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
                'message' => 'Journal created successfully',
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

    public function createVoucher(Request $request)
    {
        $request->validate([
            'qty' => 'required|numeric',
            'price' => 'required|numeric',
            'product_id' => 'required',
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
                'debt_code' => 10,
                'cred_code' => 10,
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
                'debt_code' => 10,
                'cred_code' => 10,
                'amount' => $price,
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
        $journal = new Journal();
        $request->validate([
            'debt_code' => 'required|exists:chart_of_accounts,id',
            'cred_code' => 'required|exists:chart_of_accounts,id',
            'amount' => 'required|numeric|min:0',
            'trx_type' => 'required',
        ]);

        $description = $request->description ?? 'Mutasi Kas';

        DB::beginTransaction();
        try {
            $journal->create([
                'invoice' => $journal->invoice_journal(),  // Menggunakan metode statis untuk invoice
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
                'message' => 'Mutasi Kas berhasil',
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

    public function getJournalByWarehouse($warehouse, $startDate, $endDate)
    {
        $chartOfAccounts = ChartOfAccount::where('warehouse_id', $warehouse)->orWhere('id', 10)->pluck('id')->toArray();
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfDay();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $journals = Journal::with(['debt', 'cred'])
            ->where(function ($query) use ($chartOfAccounts) {
                $query->whereIn('debt_code', $chartOfAccounts)
                    ->orWhereIn('cred_code', $chartOfAccounts);
            })
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();

        return new AccountResource($journals, true, "Successfully fetched journals");
    }

    public function getExpenses($warehouse, $startDate, $endDate)
    {
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfDay();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $expenses = Journal::with('warehouse', 'debt')
            ->where('warehouse_id', $warehouse)
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->where('trx_type', 'Pengeluaran')
            ->orderBy('id', 'desc')
            ->get();
        return new AccountResource($expenses, true, "Successfully fetched chart of accounts");
    }

    public function getWarehouseBalance()
    {
        $journal = new Journal();
        $endDate = Carbon::parse($this->endDate)->endOfDay();

        $transactions = $journal
            ->with('warehouse', 'debt', 'cred')
            ->selectRaw('debt_code, cred_code, SUM(amount) as total')
            ->whereBetween('date_issued', [Carbon::create(0000, 1, 1, 0, 0, 0)->startOfDay(), $endDate])
            ->groupBy('debt_code', 'cred_code')
            ->get();

        $chartOfAccounts = ChartOfAccount::with(['account'])->get();

        foreach ($chartOfAccounts as $value) {
            $debit = $transactions->where('debt_code', $value->id)->sum('total');
            $credit = $transactions->where('cred_code', $value->id)->sum('total');

            // @ts-ignore
            $value->balance = ($value->account->status == "D") ? ($value->st_balance + $debit - $credit) : ($value->st_balance + $credit - $debit);
        }

        $sumtotalCash = $chartOfAccounts->whereIn('account_id', ['1']);
        $sumtotalBank = $chartOfAccounts->whereIn('account_id', ['2']);

        $warehouse = Warehouse::orderBy('name', 'asc')->get();

        $data = [
            'warehouse' => $warehouse->map(function ($warehouse) use ($chartOfAccounts) {
                return [
                    'name' => $warehouse->name,
                    'cash' => $chartOfAccounts->whereIn('account_id', ['1'])->where('warehouse_id', $warehouse->id)->sum('balance'),
                    'bank' => $chartOfAccounts->whereIn('account_id', ['2'])->where('warehouse_id', $warehouse->id)->sum('balance')
                ];
            }),
            'totalCash' => $sumtotalCash->sum('balance'),
            'totalBank' => $sumtotalBank->sum('balance')
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }

    public function getRevenueReport()
    {
        $journal = new Journal();
        $startDate = Carbon::parse($this->startDate)->startOfDay();
        $endDate = Carbon::parse($this->endDate)->endOfDay();

        $revenue = $journal->with('warehouse')->selectRaw('SUM(amount) as total, warehouse_id, SUM(fee_amount) as sumfee')
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->groupBy('warehouse_id')
            ->orderBy('sumfee', 'desc')
            ->get();

        $data = [
            'revenue' => $revenue->map(function ($r) use ($startDate, $endDate) {
                $rv = $r->whereBetween('date_issued', [
                    Carbon::parse($startDate)->startOfDay(),
                    Carbon::parse($endDate)->endOfDay()
                ])->where('warehouse_id', $r->warehouse_id)->get();
                return [
                    'warehouse' => $r->warehouse->name,
                    'transfer' => $rv->where('trx_type', 'Transfer Uang')->sum('amount'),
                    'tarikTunai' => $rv->where('trx_type', 'Tarik Tunai')->sum('amount'),
                    'voucher' => $rv->where('trx_type', 'Voucher & SP')->sum('amount'),
                    'deposit' => $rv->where('trx_type', 'Deposit')->sum('amount'),
                    'trx' => $rv->count(),
                    'expense' => -$rv->where('trx_type', 'Pengeluaran')->sum('fee_amount'),
                    'fee' => $r->sumfee
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }

    public function mutationHistory(Request $request)
    {
        $request->validate([
            'account' => 'required',
        ]);

        $journal = new Journal();
        $startDate = $request->endDate ? Carbon::parse($request->endDate)->startOfDay() : Carbon::now()->startOfDay();
        $endDate = $request->endDate ? Carbon::parse($request->endDate)->endOfDay() : Carbon::now()->endOfDay();

        $chartOfAccounts = ChartOfAccount::where(fn($query) => Auth()->user()->role !== 'Administrator' ? $query->where('warehouse_id', $request->warehouse_id) : $query)->orderBy('acc_code', 'asc')->get();
        $journal = new Journal();
        $journals = $journal->with('debt.account', 'cred.account', 'warehouse', 'user')
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->where(function ($query) use ($request) {
                $query->where('invoice', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('amount', 'like', '%' . $request->search . '%');
            })
            ->where(function ($query) use ($request) {
                $query->where('debt_code', $request->account)
                    ->orWhere('cred_code', $request->account);
            })
            ->orderBy('date_issued', 'asc')
            ->paginate($request->perPage, ['*'], 'mutationHistory');

        $total = $journal->with('debt.account', 'cred.account', 'warehouse', 'user')->where('debt_code', $request->account)
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->orWhere('cred_code', $request->account)
            ->WhereBetween('date_issued', [$startDate, $endDate])
            ->orderBy('date_issued', 'asc')
            ->get();

        $initBalanceDate = Carbon::parse($startDate)->subDay(1)->endOfDay();

        $debt_total = $total->where('debt_code', $request->account)->sum('amount');
        $cred_total = $total->where('cred_code', $request->account)->sum('amount');

        $data = [
            'journals' => $journals,
            'chartOfAccounts' => $chartOfAccounts,
            'initBalance' => $journal->endBalanceBetweenDate($request->account, '0000-00-00', $initBalanceDate),
            'endBalance' => $journal->endBalanceBetweenDate($request->account, '0000-00-00', $endDate),
            'debt_total' => $debt_total,
            'cred_total' => $cred_total,
        ];

        return response()->json([
            'success' => true,
            'data' => $data
        ], 200);
    }
}
