<?php

namespace App\Http\Controllers;

use App\Models\WarehouseZone;
use Illuminate\Http\Request;

class WarehouseZoneController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $warehouseZones = WarehouseZone::all();
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
    public function update(Request $request, WarehouseZone $warehouseZone)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(WarehouseZone $warehouseZone)
    {
        //
    }
}
