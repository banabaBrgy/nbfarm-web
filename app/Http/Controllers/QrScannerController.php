<?php

namespace App\Http\Controllers;

use App\Models\Production;
use App\Models\QrRecord;
use App\Models\QrTransaction;
use App\Models\User;
use App\Services\DailyInventory;
use Carbon\Carbon;
use Illuminate\Http\Request;

class QrScannerController extends Controller
{
    public function index()
    {
        abort_unless(User::routeRole() === 'staff', 403);

        $routeRole = User::routeRole();

        return view('scan-qr', [
            'scanStoreUrl' => route($routeRole . '.scan.store'),
            'productionUrl' => route($routeRole . '.production'),
        ]);
    }

    public function store(Request $request)
    {
        abort_unless(User::routeRole() === 'staff', 403);

        app(DailyInventory::class)->applyForToday();

        $request->validate([
            'qr_code' => 'required'
        ]);

        $qrCode = trim($request->qr_code);

        /*
        ---------------------------------------
        Hanapin ang QR Record
        ---------------------------------------
        */

        $qr = QrRecord::where('qr_code', $qrCode)->first();

        if (!$qr) {
            return response()->json([
                'success' => false,
                'message' => 'QR Code not found.'
            ]);
        }

        /*
        ---------------------------------------
        Hanapin ang Production ngayong araw
        ---------------------------------------
        */

        $today = Carbon::today();

        $batchId = (int) ($qr->batch_id ?: 1);

        $production = Production::whereDate('production_date', $today)
            ->where('batch_id', $batchId)
            ->first();

        /*
        ---------------------------------------
        Kapag wala pa gumawa
        ---------------------------------------
        */

        if (!$production) {

            $production = Production::create([
                'production_date' => $today,
                'batch_id' => $batchId,
                'small_eggs' => 0,
                'medium_eggs' => 0,
                'large_eggs' => 0,
                'extra_large_eggs' => 0,
                'cracked_eggs' => 0,
                'eggs_produced' => 0
            ]);
        }

        /*
        ---------------------------------------
        Duplicate Protection
        ---------------------------------------
        */

        $exist = QrTransaction::where('production_id', $production->production_id)
            ->where('qr_record_id', $qr->id)
            ->first();

        if ($exist) {
            return response()->json([
                'success' => false,
                'message' => 'QR already scanned.'
            ]);
        }

        /*
        ---------------------------------------
        Save Transaction
        ---------------------------------------
        */

        QrTransaction::create([
            'production_id' => $production->production_id,
            'qr_record_id' => $qr->id,
            'total_eggs' => $qr->tray_count * $qr->eggs_per_tray,
            'status' => 'Scanned',
            'scanned_by' => auth()->id(),
            'scanned_at' => now()
        ]);
        /*
        ---------------------------------------
        Recompute Production
        (tray stock: eggs ÷ 30, via ProductionObserver)
        ---------------------------------------
        */

        $production->recalculateEggTotals();

        /*
        ---------------------------------------
        Return Result
        ---------------------------------------
        */

        return response()->json([
            'success' => true,
            'message' => 'QR scanned successfully.',
            'qr_code' => $qr->qr_code,
            'batch' => $qr->batch_id,
            'size' => $qr->egg_size,
            'eggs' => $qr->tray_count * $qr->eggs_per_tray,
        ]);
    }
}