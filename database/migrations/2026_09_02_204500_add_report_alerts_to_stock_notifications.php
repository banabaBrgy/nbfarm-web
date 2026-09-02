<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_notifications', 'type')) {
                $table->string('type')->default('low_stock')->after('user_id');
            }

            if (! Schema::hasColumn('stock_notifications', 'report_id')) {
                $table->foreignId('report_id')
                    ->nullable()
                    ->after('inventory_item_id')
                    ->constrained('reports')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('stock_notifications', 'report_id')) {
                $table->dropConstrainedForeignId('report_id');
            }

            if (Schema::hasColumn('stock_notifications', 'type')) {
                $table->dropColumn('type');
            }
        });
    }
};
