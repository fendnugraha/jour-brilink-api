<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\AccountResource;
use App\Models\Payroll;
use Carbon\Carbon;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $employees = Employee::with([
            'contact:id,name',
            'contact.employee_receivables',
            'attendances' => function ($query) {
                $query->whereMonth('date', now()->month)
                    ->whereYear('date', now()->year);
            }
        ])->get();

        return new AccountResource($employees, true, "Successfully fetched employees");
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
            'contact_id' => 'required|exists:contacts,id',
            'salary' => 'required|numeric',
            'commision' => 'numeric',
            'hire_date' => 'required|date',
            // 'status' => 'required|in:active,inactive,retired,terminated,resigned',
        ]);

        DB::beginTransaction();
        try {
            $employee = Employee::create([
                'contact_id' => $request->contact_id,
                'hire_date' => $request->hire_date,
                'status' => 'active',
                'salary' => $request->salary,
                'commission' => $request->commision ?? 0,
            ]);

            DB::commit();

            return response()->json(['success' => true, 'data' => $employee, 'message' => 'Employee created successfully'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        //
    }

    public function storePayroll(Request $request)
    {

        DB::beginTransaction();

        try {
            foreach ($request->employees as $item) {

                // ❌ Cegah payroll double di bulan yang sama
                $exists = Payroll::where('employee_id', $item['employee_id'])
                    ->whereMonth('payroll_date', $request->month)
                    ->whereYear('payroll_date', $request->year)
                    ->exists();

                if ($exists) {
                    throw new \Exception("Payroll sudah ada untuk salah satu karyawan");
                }

                $basicSalary = $item['basic_salary'];
                $commission  = $item['commission'] ?? 0;

                $totalBonus = collect($item['bonuses'] ?? [])
                    ->sum('amount');

                $totalDeduction = collect($item['deductions'] ?? [])
                    ->sum('amount');

                $grossPay = $basicSalary + $commission + $totalBonus;
                $netPay   = $grossPay - $totalDeduction;

                $payroll = Payroll::create([
                    'employee_id'        => $item['employee_id'],
                    'payroll_date'       => Carbon::create(
                        $request->year,
                        $request->month,
                        1
                    )->endOfMonth(),
                    'total_gross_pay'    => $grossPay,
                    'total_allowances'   => $totalBonus,
                    'total_deductions'   => $totalDeduction,
                    'net_pay'            => $netPay,
                ]);

                // 💾 Simpan bonus
                foreach ($item['bonuses'] ?? [] as $bonus) {
                    $payroll->items()->create([
                        'type' => 'allowance',
                        'item_name' => $bonus['name'],
                        'amount' => $bonus['amount'],
                    ]);
                }

                // 💾 Simpan deduction
                foreach ($item['deductions'] ?? [] as $deduction) {
                    $payroll->items()->create([
                        'type' => 'deduction',
                        'item_name' => $deduction['name'],
                        'amount' => $deduction['amount'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Payroll berhasil disimpan',
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
