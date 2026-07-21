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
        Schema::create('dispatch', function (Blueprint $table) {

            $table->id('dispatch_id');

            $table->date('dispatch_date');

            $table->string('customer_name');

            $table->integer('quantity_dispatched');

            $table->string('delivery_status');

            $table->text('remarks')->nullable();

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch');
    }
};