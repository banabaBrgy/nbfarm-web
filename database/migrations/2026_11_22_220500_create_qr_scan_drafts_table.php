<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_scan_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qr_record_id')
                ->constrained('qr_records')
                ->cascadeOnDelete();
            $table->date('harvest_date');
            $table->unsignedInteger('tray_count');
            $table->unsignedInteger('eggs_per_tray');
            $table->unsignedInteger('total_eggs');
            $table->foreignId('scanned_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('scanned_at')->useCurrent();
            $table->timestamps();

            $table->index(['qr_record_id', 'harvest_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_scan_drafts');
    }
};
