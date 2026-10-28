<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reports', 'owner_comment')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->text('owner_comment')->nullable()->after('content');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('reports', 'owner_comment')) {
            Schema::table('reports', function (Blueprint $table) {
                $table->dropColumn('owner_comment');
            });
        }
    }
};
