<?php

namespace App\Http\Controllers;

use App\Models\Journal;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\AccountResource;
use App\Models\Transaction;

class JournalController extends Controller
{
    /**
     * Display a listing of the resource.
     */
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
        //
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
        //
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
                'amount' => $price,
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
}
