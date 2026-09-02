<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->unique()->after('name');
            }

            if (! Schema::hasColumn('users', 'role')) {
                $table->string('role')->default('staff')->after('email');
            }

            if (! Schema::hasColumn('users', 'security_question')) {
                $table->string('security_question')->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'security_answer')) {
                $table->string('security_answer')->nullable()->after('security_question');
            }
        });
    }

    public function down(): void
    {
        // Columns may already exist from the original users migration.
    }
};
