<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'cancellation_reason')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('cancellation_reason', 500)->nullable();
            });
        }

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('new', 'preparing', 'ready', 'cancelled') NOT NULL DEFAULT 'new'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE orders MODIFY status ENUM('new', 'preparing', 'ready') NOT NULL DEFAULT 'new'");
        }

        if (Schema::hasColumn('orders', 'cancellation_reason')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('cancellation_reason');
            });
        }
    }
};
