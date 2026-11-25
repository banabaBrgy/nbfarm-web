<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\OwnerDispatchScan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class OwnerDispatchScanController extends Controller
{
    public function index(OwnerDispatchScan $scanner)
    {
        abort_unless(User::routeRole() === 'owner', 403);

        $data = $scanner->pageData();

        return view('owner.dispatch-scan', [
            'stockBySize' => $data['stockBySize'],
            'pendingOrders' => $data['pendingOrders'],
            'resolveUrl' => route('owner.dispatch.scan.resolve'),
            'applyUrl' => route('owner.dispatch.scan.apply'),
            'dispatchUrl' => route('owner.dispatch'),
        ]);
    }

    public function resolve(Request $request, OwnerDispatchScan $scanner): JsonResponse
    {
        abort_unless(User::routeRole() === 'owner', 403);

        $request->validate([
            'qr_code' => 'required|string|max:80',
        ]);

        try {
            return response()->json([
                'ok' => true,
                'qr' => $scanner->resolve(trim($request->input('qr_code'))),
                'page' => $scanner->pageData(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Invalid QR code.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    public function apply(Request $request, OwnerDispatchScan $scanner): JsonResponse
    {
        abort_unless(User::routeRole() === 'owner', 403);

        $validated = $request->validate([
            'qr_code' => 'required|string|max:80',
            'dispatch_id' => 'required|integer|exists:dispatches,id',
            'trays' => 'required|integer|min:1|max:999',
        ]);

        try {
            $result = $scanner->apply(
                (int) $validated['dispatch_id'],
                trim($validated['qr_code']),
                (int) $validated['trays'],
            );

            return response()->json([
                'ok' => true,
                'result' => $result,
                'page' => $scanner->pageData(),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'ok' => false,
                'message' => collect($e->errors())->flatten()->first() ?? 'Could not apply scan.',
                'errors' => $e->errors(),
                'page' => $scanner->pageData(),
            ], 422);
        }
    }
}
