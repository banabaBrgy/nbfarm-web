<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            ALTER TABLE dispatch_items
            MODIFY size ENUM('Small', 'Medium', 'Large', 'Extra Large', 'Cracked') NOT NULL
        ");
    }

    public function down(): void
    {
        DB::statement("
            ALTER TABLE dispatch_items
            MODIFY size ENUM('Small', 'Medium', 'Large') NOT NULL
        ");
    }
};
