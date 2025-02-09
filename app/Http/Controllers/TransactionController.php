<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Journal;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use App\Models\WarehouseStock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;

class TransactionController extends Controller
{
    public $startDate;
    public $endDate;

    public function __construct()
    {
        $this->startDate = Carbon::now()->startOfDay();
        $this->endDate = Carbon::now()->endOfDay();
    }
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $transactions = Transaction::with(['product', 'contact'])->orderBy('created_at', 'desc')->paginate(10);

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
        $warehouseId = auth()->user()->role->warehouse_id;
        $userId = auth()->user()->id;

        // $modal = $this->modal * $this->quantity;

        $invoice = Journal::invoice_journal();

        DB::beginTransaction();
        try {
            foreach ($request->cart as $item) {
                $journal = new Journal();
                $price = $item['price'] * $item['quantity'];
                $cost = Product::find($item['id'])->cost;
                $modal = $cost * $item['quantity'];

                $description = $request->transaction_type == 'Sales' ? "Penjualan Accessories" : "Pembelian Accessories";
                $fee = $price - $modal;

                if ($request->transaction_type == 'Sales') {
                    $journal->create([
                        'invoice' => $invoice,  // Menggunakan metode statis untuk invoice
                        'date_issued' => now(),
                        'debt_code' => 10,
                        'cred_code' => 10,
                        'amount' => $cost,
                        'fee_amount' => $fee,
                        'trx_type' => 'Accessories',
                        'description' => $description,
                        'user_id' => $userId,
                        'warehouse_id' => $warehouseId
                    ]);
                }

                $sale = new Transaction([
                    'date_issued' => now(),
                    'invoice' => $invoice,
                    'product_id' => $item['id'],
                    'quantity' => $request->transaction_type == 'Sales' ? $item['quantity'] * -1 : $item['quantity'],
                    'price' => $request->transaction_type == 'Sales' ? $item['price'] : 0,
                    'cost' => $request->transaction_type == 'Sales' ? $cost : $item['price'],
                    'transaction_type' => $request->transaction_type,
                    'contact_id' => 1,
                    'warehouse_id' => $warehouseId,
                    'user_id' => $userId
                ]);
                $sale->save();

                $product = Product::find($item['id']);
                $transaction = new Transaction();

                if ($request->transaction_type == 'Sales') {
                    $sold = Product::find($item['id'])->sold + $item['quantity'];
                    Product::find($item['id'])->update(['sold' => $sold]);

                    $product_log = $transaction->where('product_id', $product->id)->sum('quantity');
                    $end_Stock = $product->stock + $product_log;
                    Product::where('id', $product->id)->update([
                        'end_Stock' => $end_Stock,
                        'price' => $item['price'],
                    ]);

                    $updateWarehouseStock = WarehouseStock::where('warehouse_id', $warehouseId)->where('product_id', $product->id)->first();
                    $updateCurrentStock = $transaction->where('product_id', $product->id)->where('warehouse_id', $warehouseId)->sum('quantity');
                    if ($updateWarehouseStock) {
                        $updateWarehouseStock->current_stock = $updateCurrentStock;
                        $updateWarehouseStock->save();
                    } else {
                        $warehouseStock = new WarehouseStock();
                        $warehouseStock->warehouse_id = $warehouseId;
                        $warehouseStock->product_id = $product->id;
                        $warehouseStock->init_stock = 0;
                        $warehouseStock->current_stock = $updateCurrentStock;
                        $warehouseStock->save();
                    }
                } else {
                    Product::updateCostAndStock($item['id'], $item['quantity'], $item['quantity'], $item['price'], $warehouseId);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Penjualan accesories berhasil, invoice: ' . $invoice,
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
        $transaction = Transaction::find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        DB::beginTransaction();
        try {
            $transaction->delete();
            $journal = Journal::where('invoice', $transaction->invoice)->first();
            if ($journal) {
                $journal->delete();
            }
            //Update product stock
            $product = Product::find($transaction->product_id);
            $product_log = Transaction::where('product_id', $product->id)->sum('quantity');
            $end_Stock = $product->stock + $product_log;
            Product::where('id', $product->id)->update([
                'end_Stock' => $end_Stock,
                'sold' => $product->sold + $transaction->quantity
            ]);

            $updateWarehouseStock = WarehouseStock::where('warehouse_id', $transaction->warehouse_id)->where('product_id', $product->id)->first();
            if ($updateWarehouseStock) {
                $updateWarehouseStock->current_stock -= $transaction->quantity;
                $updateWarehouseStock->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            // Flash an error message
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete transaction'
            ], 500);
        }
    }

    public function getTrxVcr($warehouse, $startDate, $endDate)
    {
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfDay();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $transactions = Transaction::with('product')
            ->selectRaw('product_id, SUM(quantity) as quantity, SUM(quantity*cost) as total_cost, SUM(quantity*price) as total_price, SUM(quantity*price - quantity*cost) as total_fee')
            ->where('invoice', 'like', 'JR.BK%')
            ->where('transaction_type', 'Sales')
            ->whereHas('product', function ($query) {
                $query->where('category', 'Voucher & SP');
            })
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->where(function ($query) use ($warehouse) {
                if ($warehouse === "all") {
                    $query;
                } else {
                    $query->where('warehouse_id', $warehouse);
                }
            })
            ->groupBy('product_id')
            ->get();

        return new AccountResource($transactions, true, "Successfully fetched transactions");
    }

    public function getTrxByWarehouse($warehouse, $startDate, $endDate)
    {
        $startDate = $startDate ? Carbon::parse($startDate)->startOfDay() : Carbon::now()->startOfDay();
        $endDate = $endDate ? Carbon::parse($endDate)->endOfDay() : Carbon::now()->endOfDay();

        $transactions = Transaction::with(['product', 'contact'])
            ->whereBetween('date_issued', [$startDate, $endDate])
            ->where('warehouse_id', $warehouse)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return new AccountResource($transactions, true, "Successfully fetched transactions");
    }
}
