<?php

declare(strict_types=1);

namespace App\Reporting\Blocks\Uptime;

use App\Integrations\Support\IntegrationCategory;
use App\Reporting\Contracts\BlockType;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;

/**
 * TLS/SSL certificate expiry for the site's monitored endpoints, where the
 * monitoring provider reports it (Uptime Kuma). Each monitor's certificate is
 * rated by how soon it expires so an upcoming renewal is easy to spot.
 */
class CertificatesBlock extends BlockType
{
    public function type(): string
    {
        return 'uptime.certificates';
    }

    public function label(): string
    {
        return 'SSL certificates';
    }

    public function description(): string
    {
        return 'TLS certificate expiry for each monitored endpoint, flagged when a renewal is due soon.';
    }

    public function group(): string
    {
        return 'Uptime';
    }

    public function icon(): string
    {
        return 'globe';
    }

    public function requiresCategory(): ?IntegrationCategory
    {
        return IntegrationCategory::Monitoring;
    }

    public function options(): array
    {
        return [
            BlockOption::number('warn_days', 'Warn when expiring within (days)', 30, 7, 120),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function resolve(BlockContext $context): array
    {
        $warn = (int) $context->block->configValue('warn_days', 30);

        $snapshot = $context->reader->snapshotForCategory($context->site, IntegrationCategory::Monitoring, 'monitors', $context->range) ?? [];

        $certificates = [];
        foreach ($snapshot['monitors'] ?? [] as $monitor) {
            $days = $monitor['cert_days'] ?? null;
            if ($days === null) {
                continue;
            }

            $days = (int) $days;
            $certificates[] = [
                'name' => $monitor['name'] ?? '',
                'host' => parse_url((string) ($monitor['url'] ?? ''), PHP_URL_HOST) ?: ($monitor['name'] ?? ''),
                'days' => $days,
                'rating' => $this->rating($days, $warn),
            ];
        }

        // Soonest-expiring first so the most urgent certificate leads.
        usort($certificates, fn (array $a, array $b): int => $a['days'] <=> $b['days']);

        return [
            'has_data' => $certificates !== [],
            'certificates' => $certificates,
            'soonest' => $certificates[0]['days'] ?? null,
            'warn_days' => $warn,
        ];
    }

    private function rating(int $days, int $warn): string
    {
        return match (true) {
            $days < 14 => 'poor',
            $days < $warn => 'needs-improvement',
            default => 'good',
        };
    }
}
