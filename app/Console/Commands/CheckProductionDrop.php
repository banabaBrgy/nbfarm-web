<?php

namespace App\Console\Commands;

use App\Services\ProductionDropNotifier;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CheckProductionDrop extends Command
{
    protected $signature = 'alerts:production-drop';

    protected $description = 'Alert the owner and manager when yesterday egg production is at least 20% under the saved forecast';

    public function handle(ProductionDropNotifier $notifier): int
    {
        $yesterday = Carbon::yesterday()->toDateString();
        $notifier->check($yesterday);
        $this->info('Checked egg production drop for '.$yesterday);

        return self::SUCCESS;
    }
}
