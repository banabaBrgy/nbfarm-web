<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transactions')) {
            return;
        }

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dispatch_id')->nullable();
            $table->string('customer');
            $table->date('transaction_date');
            $table->unsignedInteger('quantity')->default(0);
            $table->decimal('price', 12, 2);
            $table->string('status')->default('Pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        // Production already had this table; do not drop it on rollback.
    }
};
