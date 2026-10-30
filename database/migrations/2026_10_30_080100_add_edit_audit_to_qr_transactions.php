<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('qr_transactions', 'edited_by')) {
                $table->foreignId('edited_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('qr_transactions', 'edited_at')) {
                $table->timestamp('edited_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('qr_transactions', function (Blueprint $table) {
            if (Schema::hasColumn('qr_transactions', 'edited_by')) {
                $table->dropConstrainedForeignId('edited_by');
            }

            if (Schema::hasColumn('qr_transactions', 'edited_at')) {
                $table->dropColumn('edited_at');
            }
        });
    }
};
