<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('stock_notifications', 'alert_date')) {
                $table->date('alert_date')->nullable()->after('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('stock_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('stock_notifications', 'alert_date')) {
                $table->dropColumn('alert_date');
            }
        });
    }
};
