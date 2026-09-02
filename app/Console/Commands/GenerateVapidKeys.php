<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateVapidKeys extends Command
{
    protected $signature = 'webpush:vapid';

    protected $description = 'Generate VAPID keys for browser push alerts';

    public function handle(): int
    {
        if (! class_exists(VAPID::class)) {
            $this->error('Install minishlink/web-push first: composer require minishlink/web-push');

            return self::FAILURE;
        }

        $keys = VAPID::createVapidKeys();

        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->line('VAPID_SUBJECT=mailto:nb-farm@localhost');

        return self::SUCCESS;
    }
}
