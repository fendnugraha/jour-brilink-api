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

use function Symfony\Component\Clock\now;

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
        $request->validate([
            'contact_id' => 'required|exists:contacts,id',
            'time_in' => 'required|date_format:H:i:s',
        ]);

        if (auth()->user()->role->role !== 'Super Admin') {
            return response()->json(['success' => false, 'message' => 'You are not authorized.'], 403);
        }

        try {
            $attendance->update([
                'contact_id' => $request->contact_id,
                'time_in' => $request->time_in,
                'approval_status' => $request->approval_status,
            ]);
            return response()->json(['success' => true, 'data' => $attendance, 'message' => 'Attendance updated successfully']);
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
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
        $warehouses = Warehouse::with([
            'contact:id,name',
            'attendance' => function ($query) use ($date) {
                $query->whereDate('date', Carbon::parse($date));
            },
            'attendance.contact:id,name',  // tidak pakai closure
            'zone'
        ])
            ->where('id', '!=', 1)
            ->where('status', 1)
            ->orderBy('name', 'asc')
            ->get();

        return response()->json(['success' => true, 'data' => $warehouses]);
    }

    public function getAttendanceByContact(Request $request)
    {
        $warehouseId = auth()->user()->role->warehouse_id;
        $date = Carbon::parse($request->date) ?? now()->format('Y-m-d');

        $att = Attendance::where('warehouse_id', $warehouseId)->whereDate('date', $date)->first();
        Log::info($att);
        $contactId = $request->contact_id ?? $att->contact_id;

        Log::info("Contact ID: " . $contactId);
        $parsed = Carbon::parse($date);

        $start = $parsed->copy()->startOfMonth();
        $end   = $parsed->copy()->endOfMonth();

        $attendance = Attendance::with('contact')->whereBetween('date', [$start, $end])
            ->where('contact_id', $contactId)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $attendance
        ]);
    }

    public function createAttendance(Request $request)
    {
        $request->validate([
            'photo' => 'required|image|max:2048', // max 2MB (aman karena sudah compress)
            'latitude' => 'required',
            'longitude' => 'required',
        ]);

        $warehouseId = auth()->user()->role->warehouse_id;
        $office = Warehouse::with('zone')->findOrFail($warehouseId);

        $distance = DistanceHelper::distanceInMeters(
            $request->latitude,
            $request->longitude,
            $office->latitude,
            $office->longitude
        );

        $contact = $request->type === 'Kasir' ? $office->contact_id : $office->zone->employee_id;

        // Batas radius dalam meter (misalnya 50m)
        $maxRadius = 50;

        if ($distance > $maxRadius) {
            return response()->json([
                'success' => false,
                'message' => "Gagal, Anda berada di luar radius cabang. Jarak: " . Number::format($distance) . " meter"
            ], 422);
        }

        $path = $request->file('photo')->store('attendance', 'public');

        $time_in = Carbon::parse(now());
        $work_start = Carbon::parse($office->opening_time);
        $diff = $time_in->diffInMinutes($work_start);
        Log::info($diff);

        $status = $time_in->gt($work_start) ? 'Late' : 'Approved';

        DB::beginTransaction();
        try {
            Attendance::create([
                'user_id' => auth()->id(),
                'contact_id' => $contact ?? null,
                'warehouse_id' => $warehouseId,
                'photo'   => $path,
                'time_in' => Carbon::parse($request->time_in)->format('H:i:s') ?? Carbon::parse(now())->format('H:i:s'),
                'date'    => now(),
                'ip'      => $request->ip(),
                'note'    => $request->note,
                'longitude' => $request->longitude,
                'latitude' => $request->latitude,
                'approval_status' => $status
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
        $attendance = Attendance::with('contact:id,name')->where('user_id', $userId)->whereDate('date', $date)->first();
        return response()->json(['success' => true, 'data' => $attendance]);
    }
}
