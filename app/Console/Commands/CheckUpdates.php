<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\UpdateChecker;
use Illuminate\Console\Command;

class CheckUpdates extends Command
{
    protected $signature = 'client-reporter:check-updates';

    protected $description = 'Check GitHub for a newer Client Reporter release';

    public function handle(UpdateChecker $checker): int
    {
        $status = $checker->check();

        if ($status['update_available']) {
            $this->info("Update available: {$status['latest']} (current {$status['current']}).");
        } else {
            $this->info("Client Reporter is up to date ({$status['current']}).");
        }

        return self::SUCCESS;
    }
}
