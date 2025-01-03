<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use Illuminate\Http\Request;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChartOfAccountResource;
use Illuminate\Support\Facades\Log;

class WarehouseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouses = Warehouse::with('ChartOfAccount')->paginate(5);
        return new ChartOfAccountResource($warehouses, true, "Successfully fetched warehouses");
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $request->validate([
            'code' => 'required|size:3|unique:warehouses,code',
            'name' => 'required|min:3|max:90',
            'address' => 'required|min:3|max:160',
            'acc_code' => 'required',
        ]);

        DB::beginTransaction();
        try {
            // Create and save the warehouse
            $warehouse = Warehouse::create([
                'code' => strtoupper($request->code),
                'name' => strtoupper($request->name),
                'address' => $request->address,
                'chart_of_account_id' => $request->acc_code
            ]);

            // Update the related ChartOfAccount with the warehouse ID
            ChartOfAccount::where('id', $request->acc_code)->update(['warehouse_id' => $warehouse->id]);

            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Warehouse created successfully',
                'data' => $warehouse
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            // Flash an error message
            Log::error($e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Warehouse creation failed',
                'data' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        $warehouse = Warehouse::with('ChartOfAccount')->find($id);

        if (!$warehouse) {
            return response()->json([
                'success' => false,
                'message' => 'Warehouse not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $warehouse
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Warehouse $warehouse)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Warehouse $warehouse)
    {
        //
    }
}
