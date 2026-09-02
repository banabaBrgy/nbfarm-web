<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\StockNotification;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

class WebPushSender
{
    public function send(StockNotification $alert): void
    {
        $user = $alert->user;

        if (! $user?->push_alerts_enabled) {
            return;
        }

        $public = (string) config('services.webpush.public_key');
        $private = (string) config('services.webpush.private_key');

        if ($public === '' || $private === '' || ! class_exists(WebPush::class)) {
            return;
        }

        $payload = json_encode([
            'title' => $alert->title ?: 'NB Farm',
            'body' => $alert->body,
            'url' => $alert->url ?: '/',
        ], JSON_UNESCAPED_UNICODE);

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => (string) config('services.webpush.subject', 'mailto:nb-farm@localhost'),
                    'publicKey' => $public,
                    'privateKey' => $private,
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('Web push client failed: '.$e->getMessage());

            return;
        }

        $subscriptions = PushSubscription::where('user_id', $user->id)->get();

        foreach ($subscriptions as $row) {
            try {
                $report = $webPush->sendOneNotification(
                    Subscription::create([
                        'endpoint' => $row->endpoint,
                        'publicKey' => $row->public_key,
                        'authToken' => $row->auth_token,
                        'contentEncoding' => $row->content_encoding ?: 'aes128gcm',
                    ]),
                    $payload
                );

                if ($report && ! $report->isSuccess()) {
                    Log::warning('Web push rejected: '.$report->getReason(), [
                        'status' => $report->getResponse()?->getStatusCode(),
                    ]);
                    $code = $report->getResponse()?->getStatusCode();
                    if (in_array($code, [404, 410], true)) {
                        $row->delete();
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Web push send failed: '.$e->getMessage());
            }
        }
    }
}
