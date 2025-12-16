<?php

namespace App\Services;

use App\Models\Finance;
use App\Models\Journal;
use App\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class EmployeeReceivableService
{
    public function pay(array $data)
    {
        $contactId   = $data['contact_id'];
        $amount      = $data['amount'];
        $accountId   = $data['account_id'];
        $notes       = $data['notes'];
        $financeType = $data['finance_type'] ?? 'EmployeeReceivable';
        $dateIssued  = isset($data['date_issued'])
            ? Carbon::parse($data['date_issued'])
            : Carbon::now();

        $sisa = $this->getRemainingReceivable($contactId, $financeType);

        if ($sisa <= 0 || $amount > $sisa) {
            throw new \Exception('Jumlah pembayaran melebihi sisa tagihan');
        }

        return DB::transaction(function () use (
            $contactId,
            $amount,
            $accountId,
            $notes,
            $financeType,
            $dateIssued,
            $sisa
        ) {
            $paymentNth = Finance::where('contact_id', $contactId)
                ->lockForUpdate()
                ->max('payment_nth') + 1;

            $invoiceNumber = Finance::payment_invoice($contactId);
            $sisaAkhir = $sisa - $amount;
            $paymentStatus = $sisaAkhir <= 0 ? 1 : 0;

            Finance::create([
                'date_issued'     => $dateIssued,
                'due_date'        => $dateIssued,
                'invoice'         => $invoiceNumber,
                'description'     => $notes,
                'bill_amount'     => 0,
                'payment_amount'  => $amount,
                'payment_status'  => $paymentStatus,
                'payment_nth'     => $paymentNth,
                'finance_type'    => $financeType,
                'contact_id'      => $contactId,
                'user_id'         => Auth::id(),
                'account_code'    => $accountId,
            ]);

            Journal::create([
                'date_issued'   => $dateIssued,
                'invoice'       => $invoiceNumber,
                'description'   => $notes,
                'debt_code'     => ChartOfAccount::EMPLOYEE_RECEIVABLE,
                'cred_code'     => $accountId,
                'amount'        => $amount,
                'fee_amount'    => 0,
                'status'        => 1,
                'rcv_pay'       => $financeType,
                'payment_status' => $paymentStatus,
                'payment_nth'   => $paymentNth,
                'user_id'       => Auth::id(),
                'warehouse_id'  => Auth::user()->warehouse_id ?? 1,
            ]);

            return [
                'payment_status' => $paymentStatus,
                'remaining' => $sisaAkhir
            ];
        });
    }

    protected function getRemainingReceivable($contactId, $financeType)
    {
        return Finance::where('contact_id', $contactId)
            ->where('finance_type', $financeType)
            ->sum(DB::raw('bill_amount - payment_amount'));
    }
}
