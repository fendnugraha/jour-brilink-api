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
        Schema::table('warehouses', function (Blueprint $table) {
            $table->time('opening_time')->nullable();
            $table->foreignId('warehouse_zone_id')->nullable()->constrained('warehouse_zones')->onDelete('set null');
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropColumn('opening_time');
            $table->dropForeign(['warehouse_zone_id']);
            $table->dropColumn('warehouse_zone_id');
            $table->dropColumn('longitude');
            $table->dropColumn('latitude');
            $table->dropForeign(['contact_id']);
            $table->dropColumn('contact_id');
        });
    }
};
