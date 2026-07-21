<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->string('username')->unique()->after('name');

            $table->string('role')->default('staff')->after('email');

            $table->string('security_question')->nullable()->after('password');

            $table->string('security_answer')->nullable()->after('security_question');

        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {

            $table->dropColumn([
                'username',
                'role',
                'security_question',
                'security_answer'
            ]);

        });
    }
};