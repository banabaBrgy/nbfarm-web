<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('reports')) {
            return;
        }

        if (! Schema::hasColumn('reports', 'report_date')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->date('report_date')->nullable();
            });
        }
    }

    public function down(): void
    {
        // report_date belongs to the original reports table on full schemas.
    }
};
