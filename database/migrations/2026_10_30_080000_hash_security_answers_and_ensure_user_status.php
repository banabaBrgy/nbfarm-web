<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status')->default('active');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
        });

        DB::table('users')
            ->whereNotNull('security_answer')
            ->orderBy('id')
            ->get(['id', 'security_answer'])
            ->each(function (object $user): void {
                $answer = (string) $user->security_answer;

                if ($answer === '' || User::isHashedSecret($answer)) {
                    return;
                }

                DB::table('users')->where('id', $user->id)->update([
                    'security_answer' => User::hashSecurityAnswer($answer),
                ]);
            });
    }

    public function down(): void
    {
        // Hashed answers cannot be restored to the original text.
    }
};
