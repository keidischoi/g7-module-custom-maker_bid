<?php

namespace Modules\Custom\MakerBids\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\Custom\MakerBids\Console\Commands\RunMarketplaceScheduleCommand;

class MakerBidsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                RunMarketplaceScheduleCommand::class,
            ]);
        }
    }
}
