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
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('contacts')->onDelete('cascade');
            $table->date('hire_date');
            $table->enum('status', ['active', 'inactive', 'retired', 'terminated', 'resigned'])->default('active');
            $table->decimal('salary', 10, 2)->default(0);
            $table->decimal('commission', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['contact_id'], 'contact_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
