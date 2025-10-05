<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Journal;
use App\Models\Correction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Http\Resources\AccountResource;

class CorrectionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $startDate = $request->start_date ? Carbon::parse($request->start_date)->startOfDay() : Carbon::now()->startOfDay();
        $endDate = $request->end_date ? Carbon::parse($request->end_date)->endOfDay() : Carbon::now()->endOfDay();

        $corrections = Correction::with(['referenceJournal.debt:id,acc_name', 'referenceJournal.cred:id,acc_name', 'warehouse:id,name', 'user:id,name'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('warehouse_id', $request->warehouse_id)
            ->orderBy('created_at', 'desc')
            ->get();

        return new AccountResource($corrections, true, "Successfully fetched corrections");
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
            'date_issued' => 'required|date',
            'journal_reference_id' => 'nullable|exists:journals,id',
            'amount' => 'required|numeric',
            'description' => 'required|max:160',
            'warehouse_id' => 'required|exists:warehouses,id',
            'image_url' => 'nullable|url',
        ]);

        try {
            $data = DB::transaction(function () use ($request) {

                $invoice = Journal::invoice_journal();

                $journal = Journal::create([
                    'invoice' => $invoice,
                    'date_issued' => now(),
                    'debt_code' => 9,
                    'cred_code' => 9,
                    'amount' => 0,
                    'fee_amount' => $request->amount,
                    'trx_type' => 'Correction',
                    'description' => "Koreksi: " . $request->description,
                    'user_id' => auth()->id(),
                    'warehouse_id' => $request->warehouse_id,
                ]);

                if ($request->journal_reference_id) {
                    $refJournal = Journal::where('id', $request->journal_reference_id)->first();
                    $refJournal->update([
                        'is_confirmed' => true
                    ]);
                }

                $correction = Correction::create([
                    'date_issued' => $refJournal->date_issued ?? $request->date_issued,
                    'journal_id' => $journal->id,
                    'journal_reference_id' => $request->journal_reference_id,
                    'warehouse_id' => $request->warehouse_id,
                    'user_id' => auth()->id(),
                    'amount' => $request->amount,
                    'description' => $request->description,
                    'image_url' => $request->image_url,
                ]);

                return $correction;
            });

            return response()->json([
                'status' => 'success',
                'data' => $data,
                'message' => 'Berhasil menyimpan data koreksi',
            ]);
        } catch (\Throwable $e) {
            Log::error('Correction Store Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan data koreksi. ' . $e->getMessage(),
            ], 500);
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(Correction $correction)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Correction $correction)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Correction $correction)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Correction $correction)
    {
        $correction->delete();
        $correction->journal->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'Berhasil menghapus data koreksi',
        ]);
    }
}
