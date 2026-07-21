<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_transactions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('qr_record_id')
                ->constrained('qr_records')
                ->cascadeOnDelete();

            $table->foreignId('production_id')
                ->constrained('production', 'production_id')
                ->cascadeOnDelete();

            $table->integer('total_eggs');

            $table->enum('status', [
                'Scanned',
                'Edited'
            ])->default('Scanned');

            $table->foreignId('scanned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('scanned_at')->useCurrent();

            $table->timestamps();

        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_transactions');
    }
};