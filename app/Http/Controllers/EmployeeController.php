<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Payroll;
use App\Models\Employee;
use Illuminate\Http\Request;
use App\Models\EmployeeWarning;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\AccountResource;
use App\Services\EmployeeReceivableService;

class EmployeeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $month = $request->month ?? now()->month;
        $year = $request->year ?? now()->year;

        $employees = Employee::with([
            'warningActive',
            'contact:id,name',
            'contact.employee_receivables_sum',
            'attendances' => function ($q) use ($month, $year) {
                $q->whereMonth('date', $month)
                    ->whereYear('date', $year);
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

        if (Employee::where('contact_id', $request->contact_id)->exists()) {
            return response()->json(['success' => false, 'message' => 'Kontak sudah menjadi karyawan'], 400);
        }

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
        $request->validate([
            // 'status' => 'in:active,inactive,retired,terminated,resigned',
            'salary' => 'numeric',
            'commission' => 'numeric',
            'hire_date' => 'date',
        ]);

        DB::beginTransaction();
        try {
            $employee->update([
                'status' => $request->status ?? $employee->status,
                'salary' => $request->salary ?? $employee->salary,
                'commission' => $request->commission ?? $employee->commission,
                'hire_date' => $request->hire_date ?? $employee->hire_date,
            ]);

            DB::commit();

            return response()->json(['success' => true, 'data' => $employee, 'message' => 'Employee updated successfully'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e->getMessage());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        //
    }

    public function storePayroll(Request $request, EmployeeReceivableService $service)
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
                    ->sum('amount') + $item['overtime'];

                $totalDeduction = collect($item['deductions'] ?? [])
                    ->sum('amount') + $item['employee_receivable'] + $item['installment_receivable'];

                $grossPay = $basicSalary + $commission + $totalBonus;
                $netPay   = $grossPay - $totalDeduction;

                $payroll = Payroll::create([
                    'employee_id'        => $item['employee_id'],
                    'payroll_date'       => Carbon::create(
                        $request->year,
                        $request->month,
                        1
                    )->endOfMonth(),
                    'total_gross_pay'    => $basicSalary,
                    'total_commissions'   => $commission,
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

                if ($item['employee_receivable'] > 0) {
                    $payroll->items()->create([
                        'type' => 'deduction',
                        'item_name' => 'Potong Kasbon',
                        'amount' => $item['employee_receivable'],
                    ]);

                    $service->pay([
                        'contact_id' => $item['contact_id'],
                        'amount' => $item['employee_receivable'],
                        'account_id' => 1,
                        'notes' => 'Potongan kasbon bulan ' . now()->format('F Y'),
                    ]);
                }

                if ($item['installment_receivable'] > 0) {
                    $payroll->items()->create([
                        'type' => 'deduction',
                        'item_name' => 'Potong Cicilan',
                        'amount' => $item['installment_receivable'],
                    ]);

                    $service->pay([
                        'contact_id' => $item['contact_id'],
                        'amount' => $item['installment_receivable'],
                        'account_id' => 1,
                        'notes' => 'Potongan kasbon bulan ' . now()->format('F Y'),
                        'finance_type' => 'InstallmentReceivable',
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

    public function getPayroll()
    {
        $payroll = Payroll::with([
            'employee.contact',
            'items',
        ])
            ->selectRaw('payroll_date, employee_id, SUM(total_gross_pay) as total_gross_pay, SUM(total_commissions) as total_commissions, SUM(total_allowances) as total_allowances, SUM(total_deductions) as total_deductions, SUM(net_pay) as net_pay')
            ->groupBy('payroll_date', 'employee_id')
            ->get();

        $payrollTotal = Payroll::selectRaw('payroll_date, SUM(total_gross_pay) as total_gross_pay, SUM(total_commissions) as total_commissions, SUM(total_allowances) as total_allowances, SUM(total_deductions) as total_deductions, SUM(net_pay) as net_pay')
            ->groupBy('payroll_date')
            ->get();

        $data = [
            'payroll' => $payroll,
            'payrollTotal' => $payrollTotal
        ];

        return new AccountResource($data, true, "Successfully fetched payroll");
    }

    public function getPayrollByDate($date)
    {
        $payroll = Payroll::with([
            'employee.contact',
            'items',
        ])
            ->where('payroll_date', $date)
            ->get();

        return response()->json(['success' => true, 'data' => $payroll]);
    }

    public function addWarning(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'level' => 'required|in:SP1,SP2,SP3',
            'reason' => 'required|string',
            'date_issued' => 'nullable|date',
        ]);

        $issuedDate = isset($validated['date_issued'])
            ? Carbon::parse($validated['date_issued'])
            : now();

        // clone agar tidak mengubah issued_date
        $expiredDate = match ($validated['level']) {
            'SP1' => $issuedDate->copy()->addMonths(3),
            'SP2' => $issuedDate->copy()->addMonths(6),
            'SP3' => $issuedDate->copy()->addMonths(6),
        };

        EmployeeWarning::create([
            'employee_id' => $validated['employee_id'],
            'level' => $validated['level'],
            'reason' => $validated['reason'],
            'issued_date' => $issuedDate,
            'expired_date' => $expiredDate,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Warning added successfully',
        ]);
    }
}
