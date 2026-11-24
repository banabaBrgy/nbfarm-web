<?php

namespace App\Http\Controllers;

use App\Exceptions\QrScanRejected;
use App\Models\User;
use App\Services\DailyInventory;
use App\Services\QrScanRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QrScannerController extends Controller
{
    public function index()
    {
        abort_unless(User::routeRole() === 'staff', 403);

        $routeRole = User::routeRole();

        return view('scan-qr', [
            'scanStoreUrl' => route($routeRole.'.scan.store'),
            'productionUrl' => route($routeRole.'.production'),
            'reviewState' => app(QrScanRecorder::class)->listingForDate(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureStaff();

        $request->validate([
            'qr_code' => 'required|string|max:80',
        ]);

        return $this->jsonResult(
            fn (QrScanRecorder $recorder) => $recorder->stage(
                trim($request->qr_code),
                $request->user()
            )
        );
    }

    public function drafts(Request $request): JsonResponse
    {
        $this->ensureStaff();

        $qrCode = trim((string) $request->query('qr_code', ''));

        return response()->json(
            app(QrScanRecorder::class)->listing($qrCode !== '' ? $qrCode : null)
        );
    }

    public function add(Request $request): JsonResponse
    {
        $this->ensureStaff();

        $request->validate([
            'qr_code' => 'required|string|max:80',
        ]);

        return $this->jsonResult(
            fn (QrScanRecorder $recorder) => $recorder->addStack(
                trim($request->qr_code),
                $request->user()
            )
        );
    }

    public function update(Request $request, int $draft): JsonResponse
    {
        $this->ensureStaff();

        $request->validate([
            'trays' => 'required|integer|min:1|max:999',
            'eggs' => 'nullable|integer|min:1|max:99999',
        ]);

        return $this->jsonResult(
            fn (QrScanRecorder $recorder) => $recorder->updateDraft(
                $draft,
                $request->integer('trays'),
                $request->user(),
                $request->filled('eggs') ? $request->integer('eggs') : null
            )
        );
    }

    public function destroy(Request $request, int $draft): JsonResponse
    {
        $this->ensureStaff();

        return $this->jsonResult(
            fn (QrScanRecorder $recorder) => $recorder->deleteDraft(
                $draft,
                $request->user()
            )
        );
    }

    public function commit(Request $request): JsonResponse
    {
        $this->ensureStaff();

        $request->validate([
            'qr_code' => 'nullable|string|max:80',
            'harvest_date' => 'nullable|date',
        ]);

        $qrCode = trim((string) $request->input('qr_code', ''));

        try {
            app(DailyInventory::class)->applyForToday();
            $recorder = app(QrScanRecorder::class);

            if ($qrCode !== '') {
                return response()->json(
                    $recorder->commit($qrCode, $request->user())
                );
            }

            return response()->json(
                $recorder->commitAll(
                    $request->input('harvest_date'),
                    $request->user()
                )
            );
        } catch (QrScanRejected $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function ensureStaff(): void
    {
        abort_unless(User::routeRole() === 'staff', 403);
    }

    private function jsonResult(callable $callback): JsonResponse
    {
        try {
            return response()->json($callback(app(QrScanRecorder::class)));
        } catch (QrScanRejected $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}
