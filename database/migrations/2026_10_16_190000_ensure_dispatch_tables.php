<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('dispatches')) {
            Schema::create('dispatches', function (Blueprint $table) {
                $table->id();
                $table->string('recipient');
                $table->string('contact', 30)->nullable();
                $table->string('address');
                $table->date('dispatch_date');
                $table->decimal('price', 12, 2);
                $table->string('status');
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('dispatch_items')) {
            Schema::create('dispatch_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('dispatch_id')
                    ->constrained('dispatches')
                    ->cascadeOnDelete();
                $table->string('size');
                $table->unsignedInteger('quantity');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('transactions')) {
            if (! Schema::hasColumn('transactions', 'dispatch_id')) {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->foreignId('dispatch_id')
                        ->nullable()
                        ->after('id');
                });
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

        if (! Schema::hasTable('dispatch_stock_locks')) {
            Schema::create('dispatch_stock_locks', function (Blueprint $table) {
                $table->unsignedTinyInteger('id')->primary();
            });
        }

        DB::table('dispatch_stock_locks')->insertOrIgnore(['id' => 1]);
    }

    public function down(): void
    {
        // The dispatch tables may predate this ensure migration, so preserve them on rollback.
        Schema::dropIfExists('dispatch_stock_locks');
    }
};
