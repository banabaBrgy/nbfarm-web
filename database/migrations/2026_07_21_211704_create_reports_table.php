<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {

            $table->id();

            $table->string('report_no')->unique();

            $table->foreignId('manager_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->string('title');

            $table->longText('content');

            $table->date('report_date');

            $table->enum('status',[
                'Draft',
                'Sent',
                'Read'
            ])->default('Draft');

            $table->timestamp('sent_at')->nullable();

            $table->timestamp('read_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
