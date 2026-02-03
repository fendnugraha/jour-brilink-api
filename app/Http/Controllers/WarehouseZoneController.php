<?php

namespace App\Http\Controllers;

use App\Models\WarehouseZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WarehouseZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouseZones = WarehouseZone::with('contact')->get();
        return response()->json([
            'success' => true,
            'data' => $warehouseZones
        ]);
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
            'zone_name' => 'required|unique:warehouse_zones,zone_name',
            'employee_id' => 'required|exists:contacts,id'
        ]);

        $warehouseZone = WarehouseZone::create($request->all());
        return response()->json([
            'success' => true,
            'data' => $warehouseZone
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(WarehouseZone $warehouseZone)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(WarehouseZone $warehouseZone)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, WarehouseZone $zone)
    {
        Log::info('Warehouse zone updated', ['zone_id' => $zone->id, 'updated_by' => auth()->user()->id]);

        $request->validate([
            'zone_name' => 'required',
            'employee_id' => 'required|exists:contacts,id'
        ]);

        try {
            $zone->update($request->all());
            return response()->json([
                'success' => true,
                'message' => 'Warehouse zone updated successfully',
                'data' => $zone
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WarehouseZone $warehouseZone)
    {
        //
    }
}
