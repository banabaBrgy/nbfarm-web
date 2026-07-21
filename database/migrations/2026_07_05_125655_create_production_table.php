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
        Schema::create('production', function (Blueprint $table) {

            $table->id('production_id');

            $table->date('production_date');

            $table->unsignedTinyInteger('batch_id');

            $table->integer('small_eggs');

            $table->integer('medium_eggs');

            $table->integer('large_eggs');

            $table->integer('extra_large_eggs');

            $table->integer('cracked_eggs');

            $table->integer('eggs_produced');

            $table->timestamps();

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('production');
    }
};