<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * A convenience helper for the post-pull update steps. It does NOT download or
 * replace application code — pull the new release with git/composer first, then
 * run this to finish the upgrade safely.
 */
class Update extends Command
{
    protected $signature = 'client-reporter:update {--force : Skip the confirmation prompt}';

    protected $description = 'Run database migrations and clear caches after updating Client Reporter';

    public function handle(): int
    {
        $this->info('Finishing the Client Reporter update…');

        if (! $this->option('force') && ! $this->confirm('Run migrations and clear caches now?', true)) {
            return self::SUCCESS;
        }

        $this->call('migrate', ['--force' => true]);
        $this->call('optimize:clear');

        $this->info('Done. Reload the application to use the new version.');

        return self::SUCCESS;
    }
}
