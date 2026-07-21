<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('revenue', function (Blueprint $table) {

            $table->id('revenue_id');

            $table->unsignedBigInteger('dispatch_id');

            $table->date('transaction_date');

            $table->string('customer_name');

            $table->decimal('total_amount', 12, 2);

            $table->timestamps();

            $table->foreign('dispatch_id')
                  ->references('dispatch_id')
                  ->on('dispatch')
                  ->onDelete('cascade');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('revenue');
    }
};