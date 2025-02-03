<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Journal;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;

class TransactionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $transactions = Transaction::with(['product', 'contact'])->orderBy('created_at', 'desc')->paginate(5);

        return new AccountResource($transactions, true, "Successfully fetched transactions");
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
        $request->validate([
            'cart' => 'required|array',
            'transaction_type' => 'required|string',
        ]);


        // $modal = $this->modal * $this->quantity;

        $invoice = Journal::invoice_journal();

        DB::beginTransaction();
        Log::debug('Transaction started');
        try {
            foreach ($request->cart as $item) {
                $journal = new Journal();
                $price = $item['price'] * $item['quantity'];
                $cost = Product::find($item['id'])->cost;
                $modal = $cost * $item['quantity'];

                $description = "Penjualan Accessories";
                $fee = $price - $modal;

                $journal->create([
                    'invoice' => $invoice,  // Menggunakan metode statis untuk invoice
                    'date_issued' => now(),
                    'debt_code' => 10,
                    'cred_code' => 10,
                    'amount' => $cost,
                    'fee_amount' => $fee,
                    'trx_type' => 'Accessories',
                    'description' => $description,
                    'user_id' => auth()->user()->id,
                    'warehouse_id' => auth()->user()->role->warehouse_id
                ]);

                $sale = new Transaction([
                    'date_issued' => now(),
                    'invoice' => $invoice,
                    'product_id' => $item['id'],
                    'quantity' => $request->transaction_type == 'Sales' ? $item['quantity'] * -1 : $item['quantity'],
                    'price' => $item['price'],
                    'cost' => $cost,
                    'transaction_type' => $request->transaction_type,
                    'contact_id' => 1,
                    'warehouse_id' => auth()->user()->role->warehouse_id,
                    'user_id' => auth()->user()->id
                ]);
                $sale->save();

                $sold = Product::find($item['id'])->sold + $item['quantity'];
                Product::find($item['id'])->update(['sold' => $sold]);
            }

            DB::commit();
            Log::debug('Transaction committed');

            return response()->json([
                'message' => 'Penjualan accesories berhasil, invoice: ' . $invoice,
                'journal' => $journal
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::debug('Transaction rolled back');
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to create journal'
            ], 500);
        }
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
    public function destroy(string $id)
    {
        //
    }

    public function getTrxVcr($warehouse, $startDate, $endDate)
    {
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfDay();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $transactions = Transaction::with('product')
            ->selectRaw('product_id, SUM(quantity) as quantity, SUM(quantity*cost) as total_cost, SUM(quantity*price) as total_price, SUM(quantity*price - quantity*cost) as total_fee')
            ->where('invoice', 'like', 'JR.BK%')
            ->whereHas('product', function ($query) {
                $query->where('category', 'Voucher & SP');
            })
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->where('warehouse_id', $warehouse)
            ->groupBy('product_id')
            ->get();

        return new AccountResource($transactions, true, "Successfully fetched transactions");
    }
}
