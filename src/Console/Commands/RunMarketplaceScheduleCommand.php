<?php

namespace Modules\Custom\MakerBids\Console\Commands;

use Illuminate\Console\Command;
use Modules\Custom\MakerBids\Services\MarketplaceService;

class RunMarketplaceScheduleCommand extends Command
{
    protected $signature = 'maker-bids:run-schedule';

    protected $description = 'Close expired maker_bids jobs and send deadline-soon notices';

    public function handle(MarketplaceService $market): int
    {
        $result = $market->runSchedule();
        $this->info('closed='.$result['closed'].' deadline_notices='.$result['deadline_notices']);

        return self::SUCCESS;
    }
}
