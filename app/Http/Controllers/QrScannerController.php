<?php

namespace App\Http\Controllers;

use App\Models\QrRecord;
use App\Models\QrTransaction;
use App\Models\Production;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\Inventory;

class QrScannerController extends Controller
{
    public function index()
    {
        return view('scan-qr');
    }

    public function store(Request $request)
    {
        $request->validate([
            'qr_code' => 'required'
        ]);

        /*
        ---------------------------------------
        Hanapin ang QR Record
        ---------------------------------------
        */

        $qr = QrRecord::where('qr_code', $request->qr_code)->first();

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

        $production = Production::whereDate('production_date', $today)
            ->where('batch_id', $qr->batch_id)
            ->first();

        /*
        ---------------------------------------
        Kapag wala pa gumawa
        ---------------------------------------
        */

        if (!$production) {

            $production = Production::create([
                'production_date' => $today,
                'batch_id' => $qr->batch_id,
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
        Deduct Empty Egg Trays
        ---------------------------------------
        */

        $eggTray = Inventory::where('item_type', 'Egg Tray')->first();

        if ($eggTray) {

            $eggTray->quantity = max(
                0,
                $eggTray->quantity - $qr->tray_count
            );

            $eggTray->save();
        }

        /*
        ---------------------------------------
        Recompute Production
        ---------------------------------------
        */

        $transactions = QrTransaction::with('qrRecord')
            ->where('production_id', $production->production_id)
            ->get();

        $small = 0;
        $medium = 0;
        $large = 0;
        $xl = 0;
        $cracked = 0;

        foreach ($transactions as $t) {

            switch ($t->qrRecord->egg_size) {

                case 'Small':
                    $small += $t->total_eggs;
                    break;

                case 'Medium':
                    $medium += $t->total_eggs;
                    break;

                case 'Large':
                    $large += $t->total_eggs;
                    break;

                case 'Extra Large':
                    $xl += $t->total_eggs;
                    break;

                case 'Cracked':
                    $cracked += $t->total_eggs;
                    break;
            }
        }

        $production->update([
            'small_eggs' => $small,
            'medium_eggs' => $medium,
            'large_eggs' => $large,
            'extra_large_eggs' => $xl,
            'cracked_eggs' => $cracked,
            'eggs_produced' => $small + $medium + $large + $xl + $cracked
        ]);

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