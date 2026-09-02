<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasTable('dispatches')) {
            return;
        }

        if (! Schema::hasColumn('transactions', 'dispatch_id')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->foreignId('dispatch_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('dispatches')
                    ->cascadeOnDelete();
                $table->unique('dispatch_id');
            });
        }

        $dispatches = DB::table('dispatches')->orderBy('id')->get();

        foreach ($dispatches as $dispatch) {
            if (DB::table('transactions')->where('dispatch_id', $dispatch->id)->exists()) {
                continue;
            }

            $qty = (int) DB::table('dispatch_items')
                ->where('dispatch_id', $dispatch->id)
                ->sum('quantity');
            $saleStatus = $dispatch->status === 'Delivered' ? 'Paid' : 'Pending';

            $matchQuery = DB::table('transactions')
                ->whereNull('dispatch_id')
                ->where('customer', $dispatch->recipient)
                ->where('price', $dispatch->price)
                ->where('status', $saleStatus);

            $match = $matchQuery->orderBy('id')->first();

            if ($match) {
                DB::table('transactions')->where('id', $match->id)->update([
                    'dispatch_id' => $dispatch->id,
                    'status' => $saleStatus,
                    'quantity' => $qty > 0 ? $qty : $match->quantity,
                    'updated_at' => now(),
                ]);

                continue;
            }

            $row = [
                'dispatch_id' => $dispatch->id,
                'customer' => $dispatch->recipient,
                'transaction_date' => $dispatch->dispatch_date,
                'quantity' => $qty,
                'price' => $dispatch->price,
                'status' => $saleStatus,
            ];

            if (Schema::hasColumn('transactions', 'created_at')) {
                $row['created_at'] = now();
                $row['updated_at'] = now();
            }

            DB::table('transactions')->insert($row);
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('transactions') || ! Schema::hasColumn('transactions', 'dispatch_id')) {
            return;
        }

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['dispatch_id']);
            $table->dropConstrainedForeignId('dispatch_id');
        });
    }
};
