<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('transactions')
            || ! Schema::hasTable('dispatches')
            || ! Schema::hasColumn('transactions', 'dispatch_id')
        ) {
            return;
        }

        $hasDispatchIndex = collect(Schema::getIndexes('transactions'))
            ->contains(function (array $index): bool {
                return $index['unique']
                    && $index['columns'] === ['dispatch_id'];
            });

        if (! $hasDispatchIndex) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->unique('dispatch_id');
            });
        }

        $hasDispatchForeignKey = collect(Schema::getForeignKeys('transactions'))
            ->contains(function (array $foreignKey): bool {
                return $foreignKey['columns'] === ['dispatch_id']
                    && $foreignKey['foreign_table'] === 'dispatches';
            });

        if (! $hasDispatchForeignKey) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreign('dispatch_id')
                    ->references('id')
                    ->on('dispatches')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        // Keep production transaction data intact during rollback.
    }
};
