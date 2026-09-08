<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Integrations\PageSpeed\PageSpeedClient;
use App\Integrations\PageSpeed\PageSpeedIntegration;
use App\Models\SiteIntegration;
use App\Models\WorkspaceIntegration;
use Illuminate\Console\Command;
use Throwable;

/**
 * Diagnoses the Google API key each PageSpeed connection resolves to and,
 * optionally, makes a live call to prove it works — so a "rate-limited" error
 * can be traced to a missing/unfound key rather than guessed at.
 */
class PageSpeedKey extends Command
{
    protected $signature = 'client-reporter:pagespeed-key {--test : Make a live PageSpeed call for each connection}';

    protected $description = 'Show which Google API key each PageSpeed connection resolves, and optionally test it live.';

    public function handle(): int
    {
        $this->line('Workspace PageSpeed connections:');
        $workspaces = WorkspaceIntegration::query()->where('integration_key', 'pagespeed')->get();
        if ($workspaces->isEmpty()) {
            $this->line('  (none)');
        }
        foreach ($workspaces as $w) {
            $key = trim((string) $w->credential('api_key'));
            $this->line(sprintf('  #%d %s — %s', $w->id, $w->name, $key !== '' ? 'key '.$this->mask($key) : 'NO KEY'));
        }

        $this->newLine();
        $this->line('Shared key resolved anywhere in the workspace: '
            .(($shared = PageSpeedIntegration::sharedApiKey()) ? $this->mask($shared) : '(none)'));

        $this->newLine();
        $this->line('PageSpeed site connections:');
        $connections = SiteIntegration::query()
            ->where('integration_key', 'pagespeed')
            ->with(['site', 'workspaceIntegration'])
            ->get();

        if ($connections->isEmpty()) {
            $this->line('  (none)');
        }

        foreach ($connections as $c) {
            $key = PageSpeedIntegration::apiKeyFor($c);
            $this->line(sprintf(
                '  #%d %s — source=%s, key=%s%s',
                $c->id,
                $c->site->url,
                PageSpeedIntegration::apiKeySource($c),
                $key ? $this->mask($key) : '(none — anonymous, will be rate-limited)',
                $c->workspace_integration_id ? ' [workspace-linked]' : '',
            ));

            if ($this->option('test')) {
                try {
                    (new PageSpeedClient($key))->analyze($c->site->url, (string) ($c->setting('strategy') ?: 'mobile'));
                    $this->info('       live test: OK');
                } catch (Throwable $e) {
                    $this->error('       live test: '.$e->getMessage());
                }
            }
        }

        return self::SUCCESS;
    }

    private function mask(string $key): string
    {
        return strlen($key) <= 8 ? '****' : substr($key, 0, 4).'…'.substr($key, -3);
    }
}
