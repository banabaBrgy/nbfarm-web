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

        if (! Schema::hasColumn('reports', 'sent_at')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->timestamp('sent_at')->nullable();
            });
        }

        if (! Schema::hasColumn('reports', 'read_at')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->timestamp('read_at')->nullable();
            });
        }

        if (! Schema::hasColumn('reports', 'report_type')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->string('report_type')->nullable();
            });
        }

        if (! Schema::hasColumn('reports', 'is_read')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->boolean('is_read')->default(false);
            });
        }

        if (! Schema::hasColumn('reports', 'report_date')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->date('report_date')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('reports')) {
            return;
        }

        foreach (['report_type', 'is_read'] as $column) {
            if (Schema::hasColumn('reports', $column)) {
                Schema::table('reports', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }
};
