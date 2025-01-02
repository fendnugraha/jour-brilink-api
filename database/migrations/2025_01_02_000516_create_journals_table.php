<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date_issued')->index();

            // Kolom invoice yang berelasi dengan tabel lain
            $table->string('invoice', 60)->index();
            $table->string('description', 160);
            $table->string('debt_code', 60)->index();
            $table->string('cred_code', 60)->index();
            $table->integer('amount');
            $table->integer('fee_amount');
            $table->integer('status')->default(1);
            $table->string('trx_type', 60)->nullable();
            $table->string('rcv_pay', 30)->nullable();
            $table->integer('payment_status')->nullable();
            $table->integer('payment_nth')->nullable();

            // Relasi dengan tabel users dan warehouses
            $table->foreignId('user_id')->index();
            $table->foreignId('warehouse_id')->index();
            $table->string('serial_number', 255)->nullable();

            // Menambahkan foreign key untuk user_id dan warehouse_id dengan restrict on delete
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');

            // Menambahkan foreign key untuk invoice yang berelasi dengan 3 tabel: receivables, payables, sales
            $table->foreign('invoice')
                ->references('invoice')->on('receivables')
                ->onDelete('restrict')
                ->name('journals_receivables_invoice_foreign'); // Nama constraint unik

            $table->foreign('invoice')
                ->references('invoice')->on('payables')
                ->onDelete('restrict')
                ->name('journals_payables_invoice_foreign'); // Nama constraint unik

            $table->foreign('invoice')
                ->references('invoice')->on('sales')
                ->onDelete('restrict')
                ->name('journals_sales_invoice_foreign'); // Nama constraint unik

            $table->foreign('invoice')
                ->references('invoice')->on('purchases')
                ->onDelete('restrict')
                ->name('journals_purchases_invoice_foreign');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
