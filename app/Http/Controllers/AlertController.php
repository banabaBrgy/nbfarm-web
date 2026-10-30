<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\StockNotification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->farmLead($request);

        $alerts = StockNotification::query()
            ->where('user_id', $user->id)
            ->open()
            ->latest()
            ->limit(30)
            ->get()
            ->map(fn (StockNotification $row) => [
                'id' => $row->id,
                'title' => $row->title,
                'body' => $row->body,
                'url' => $this->alertUrl($row, $user),
                'read' => $row->read_at !== null,
                'when' => $row->created_at?->diffForHumans(),
            ]);

        return response()->json([
            'unread' => StockNotification::query()->where('user_id', $user->id)->unread()->count(),
            'push_enabled' => (bool) $user->push_alerts_enabled,
            'vapid_public' => (string) config('services.webpush.public_key'),
            'alerts' => $alerts,
        ]);
    }

    public function read(Request $request, StockNotification $alert): JsonResponse
    {
        $user = $this->farmLead($request);

        abort_unless($alert->user_id === $user->id, 403);

        if ($alert->read_at === null) {
            $alert->update(['read_at' => now()]);
        }

        return response()->json([
            'ok' => true,
            'read' => true,
            'url' => $this->alertUrl($alert, $user),
            'unread' => StockNotification::query()->where('user_id', $user->id)->unread()->count(),
        ]);
    }

    public function unread(Request $request, StockNotification $alert): JsonResponse
    {
        $user = $this->farmLead($request);

        abort_unless($alert->user_id === $user->id, 403);

        if ($alert->read_at !== null) {
            $alert->update(['read_at' => null]);
        }

        return response()->json([
            'ok' => true,
            'read' => false,
            'unread' => StockNotification::query()->where('user_id', $user->id)->unread()->count(),
        ]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = $this->farmLead($request);

        StockNotification::query()
            ->where('user_id', $user->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json([
            'ok' => true,
            'unread' => 0,
        ]);
    }

    public function togglePush(Request $request): JsonResponse
    {
        $user = $this->farmLead($request);

        $enabled = $request->boolean('enabled');
        $user->push_alerts_enabled = $enabled;
        $user->save();

        if (! $enabled) {
            PushSubscription::where('user_id', $user->id)->delete();
        }

        return response()->json([
            'ok' => true,
            'push_enabled' => $enabled,
            'vapid_public' => (string) config('services.webpush.public_key'),
        ]);
    }

    public function subscribe(Request $request): JsonResponse
    {
        $user = $this->farmLead($request);

        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'max:4000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'string', 'max:40'],
        ]);

        $endpoint = $validated['endpoint'];

        PushSubscription::updateOrCreate(
            ['endpoint_hash' => hash('sha256', $endpoint)],
            [
                'user_id' => $user->id,
                'endpoint' => $endpoint,
                'public_key' => $validated['keys']['p256dh'],
                'auth_token' => $validated['keys']['auth'],
                'content_encoding' => $validated['contentEncoding'] ?? 'aes128gcm',
            ]
        );

        $user->push_alerts_enabled = true;
        $user->save();

        return response()->json(['ok' => true]);
    }

    public function unsubscribe(Request $request): JsonResponse
    {
        $user = $this->farmLead($request);

        $endpoint = (string) $request->input('endpoint', '');

        if ($endpoint !== '') {
            PushSubscription::where('user_id', $user->id)
                ->where('endpoint_hash', hash('sha256', $endpoint))
                ->delete();
        }

        return response()->json(['ok' => true]);
    }

    private function alertUrl(StockNotification $alert, User $user): string
    {
        if ($alert->type === 'report_remark' && $alert->report_id && User::routeRole($user->role) === 'manager') {
            return '/manager/reports/'.$alert->report_id;
        }

        return $alert->url ?: route(User::routeRole($user->role).'.inventory');
    }

    private function farmLead(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->isFarmLead(), 403);

        return $user;
    }
}
