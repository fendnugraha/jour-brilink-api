<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Finance;
use App\Models\Journal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Http\Resources\AccountResource;
use Illuminate\Support\Facades\Log;

class FinanceController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $finance = Finance::with(['contact', 'account'])->orderBy('created_at', 'desc')->get();
        $financeGroupByContactId = Finance::with('contact')->selectRaw('contact_id, SUM(bill_amount) as tagihan, SUM(payment_amount) as terbayar, SUM(bill_amount) - SUM(payment_amount) as sisa, finance_type')
            ->groupBy('contact_id', 'finance_type')->get();

        $data = [
            'finance' => $finance,
            'financeGroupByContactId' => $financeGroupByContactId
        ];

        return new AccountResource($data, true, "Successfully fetched finances");
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
        $dateIssued = $request->date_issued ? Carbon::parse($request->date_issued) : Carbon::now();
        $pay = new Finance();
        $invoice_number = $pay->invoice_finance($request->contact_id, $request->type);

        $request->validate([
            'amount' => 'required|numeric',
            'description' => 'required|max:160',
            'contact_id' => 'required|exists:contacts,id',
            'debt_code' => 'required|exists:chart_of_accounts,id',
            'cred_code' => 'required|exists:chart_of_accounts,id',
        ]);

        DB::beginTransaction();
        try {
            Finance::create([
                'date_issued' => $dateIssued,
                'due_date' => $dateIssued->copy()->addDays(30),
                'invoice' => $invoice_number,
                'description' => $request->description,
                'bill_amount' => $request->amount,
                'payment_amount' => 0,
                'payment_status' => 0,
                'payment_nth' => 0,
                'finance_type' => $request->type,
                'contact_id' => $request->contact_id,
                'user_id' => auth()->user()->id,
                'account_code' => $request->type == 'Payable' ? $request->cred_code : $request->debt_code
            ]);

            Journal::create([
                'date_issued' => $dateIssued,
                'invoice' => $invoice_number,
                'description' => $request->description,
                'debt_code' => $request->debt_code,
                'cred_code' => $request->cred_code,
                'amount' => $request->amount,
                'fee_amount' => 0,
                'status' => 1,
                'rcv_pay' => $request->type,
                'payment_status' => 0,
                'payment_nth' => 0,
                'user_id' => auth()->user()->id,
                'warehouse_id' => 1
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Payable created successfully'
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
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
        $finance = Finance::find($id);
        $invoice = $finance->invoice;

        $checkData = Finance::where('invoice', $invoice)->get();
        // dd($checkData->count());

        if ($finance->payment_status == 1) {
            return response()->json([
                'status' => false,
                'message' => 'Pembayaran sudah dilakukan'
            ]);
        }


        if ($finance->payment_status == 0 && $finance->payment_nth == 0 && $checkData->count() > 1) {
            return response()->json([
                'status' => false,
                'message' => 'Sudah terjadi pembayaran'
            ]);
        }

        DB::beginTransaction();
        try {
            Journal::where('invoice', $invoice)->where('payment_status', $finance->payment_status)->where('payment_nth', $finance->payment_nth)->delete();
            $finance->delete();

            DB::commit();
            return response()->json([
                'status' => true,
                'message' => 'Payable deleted successfully'
            ]);
        } catch (\Throwable $th) {
            DB::rollBack();
            Log::error($th->getMessage());
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function getFinanceByContactId($contactId)
    {
        $finance = Finance::with(['contact', 'account'])
            ->selectRaw('contact_id, SUM(bill_amount) as tagihan, SUM(payment_amount) as terbayar, SUM(bill_amount) - SUM(payment_amount) as sisa, finance_type, invoice')
            ->groupBy('contact_id', 'finance_type', 'invoice')
            ->where('contact_id', $contactId)
            ->get();

        return new AccountResource($finance, true, "Successfully fetched finances");
    }
}
