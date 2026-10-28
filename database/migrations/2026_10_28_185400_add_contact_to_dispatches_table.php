<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('dispatches') && ! Schema::hasColumn('dispatches', 'contact')) {
            Schema::table('dispatches', function (Blueprint $table) {
                $table->string('contact', 30)->nullable()->after('recipient');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('dispatches') && Schema::hasColumn('dispatches', 'contact')) {
            Schema::table('dispatches', function (Blueprint $table) {
                $table->dropColumn('contact');
            });
        }
    }
};
