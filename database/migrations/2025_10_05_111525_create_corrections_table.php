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
        Schema::create('corrections', function (Blueprint $table) {
            $table->id();
            $table->dateTime('date_issued')->index();
            $table->foreignId('journal_id')
                ->nullable()
                ->constrained('journals')
                ->onDelete('restrict');
            $table->foreignId('journal_reference_id')
                ->nullable()
                ->constrained('journals')
                ->onDelete('restrict');
            $table->foreignId('warehouse_id')
                ->constrained('warehouses')
                ->onDelete('restrict');
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('restrict');
            $table->decimal('amount', 15, 2);
            $table->text('description');
            $table->string('image_url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('corrections');
    }
};
