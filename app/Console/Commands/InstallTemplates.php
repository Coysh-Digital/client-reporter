<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ReportTemplate;
use Database\Seeders\ReportTemplateSeeder;
use Illuminate\Console\Command;

/**
 * Installs the out-of-the-box report templates on an existing install. Fresh
 * installs get these from the installer; this command backfills them for sites
 * set up before the templates shipped. Idempotent — templates already present
 * (matched by name) are left untouched, including any edits.
 */
class InstallTemplates extends Command
{
    protected $signature = 'client-reporter:install-templates';

    protected $description = 'Install the out-of-the-box report templates';

    public function handle(ReportTemplateSeeder $seeder): int
    {
        $before = ReportTemplate::query()->count();

        $seeder->setCommand($this)->run();

        $added = ReportTemplate::query()->count() - $before;

        $this->info($added === 0
            ? 'All out-of-the-box templates are already installed; nothing to add.'
            : "Installed {$added} report template(s).");

        return self::SUCCESS;
    }
}
