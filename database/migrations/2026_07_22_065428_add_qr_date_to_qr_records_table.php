<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qr_records', function (Blueprint $table) {

            // alisin ang dating unique qr_code
            $table->dropUnique('qr_records_qr_code_unique');

            // idagdag ang date
            $table->date('qr_date')
                ->after('qr_code')
                ->default(now()->toDateString());

            // same QR allowed on different dates
            $table->unique([
                'qr_code',
                'qr_date'
            ]);

        });
    }


    public function down(): void
    {
        Schema::table('qr_records', function (Blueprint $table) {

            $table->dropUnique([
                'qr_code',
                'qr_date'
            ]);

            $table->dropColumn('qr_date');

            $table->unique('qr_code');

        });
    }
};