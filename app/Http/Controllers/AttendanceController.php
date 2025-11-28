<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Number;
use App\Helpers\DistanceHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function show(Attendance $attendance)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Attendance $attendance)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Attendance $attendance)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Attendance $attendance)
    {
        //
    }

    public function getWarehouseAttendance($date)
    {
        $warehouses = Warehouse::with(['attendance' => function ($query) use ($date) {
            $query->whereDate('date', Carbon::parse($date)->toDateString());
        }])->get();

        return response()->json(['success' => true, 'data' => $warehouses]);
    }

    public function createAttendance(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:2048', // max 2MB (aman karena sudah compress)
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $warehouseId = auth()->user()->role->warehouse_id;
        $office = Warehouse::find($warehouseId);

        $distance = DistanceHelper::distanceInMeters(
            $request->latitude,
            $request->longitude,
            $office->latitude,
            $office->longitude
        );

        $contact = $request->type === 'Kasir' ? $office->contact_id : null;

        // Batas radius dalam meter (misalnya 50m)
        $maxRadius = 50;

        if ($distance > $maxRadius) {
            return response()->json([
                'success' => false,
                'message' => "Gagal, Anda berada di luar radius kantor. Jarak: " . Number::format($distance) . " meter"
            ], 422);
        }

        $path = $request->file('photo')->store('attendance', 'public');

        DB::beginTransaction();
        try {
            Attendance::create([
                'user_id' => auth()->id(),
                'contact_id' => $contact ?? null,
                'warehouse_id' => $warehouseId,
                'photo'   => $path,
                'date'    => now(),
                'ip'      => $request->ip(),
                'note'    => $request->note,
                'longitude' => $request->longitude,
                'latitude' => $request->latitude,
            ]);

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function attendanceCheck($date, $userId)
    {
        $attendance = Attendance::where('user_id', $userId)->whereDate('date', $date)->first();
        return response()->json(['success' => true, 'data' => $attendance]);
    }
}
