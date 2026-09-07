<?php

declare(strict_types=1);

namespace App\Integrations\Support;

/**
 * Turns a list of email campaigns (from Mailchimp, EmailOctopus, …) into the
 * provider-agnostic email.* metrics the campaigns report block reads. Kept in
 * one place so every email-marketing collector emits the same shape.
 */
final class CampaignMetrics
{
    /**
     * @param  array<int, array{recipients?: int, opens?: int, clicks?: int, unsubscribed?: int|null}>  $campaigns
     */
    public static function apply(CollectorResult $result, array $campaigns): void
    {
        $recipients = 0;
        $opens = 0;
        $clicks = 0;
        $unsubscribed = 0;

        foreach ($campaigns as $campaign) {
            $recipients += (int) ($campaign['recipients'] ?? 0);
            $opens += (int) ($campaign['opens'] ?? 0);
            $clicks += (int) ($campaign['clicks'] ?? 0);
            $unsubscribed += (int) ($campaign['unsubscribed'] ?? 0);
        }

        $result
            ->metric('email.campaigns_sent', count($campaigns))
            ->metric('email.recipients', $recipients)
            ->metric('email.open_rate', self::rate($opens, $recipients), '%')
            ->metric('email.click_rate', self::rate($clicks, $recipients), '%')
            ->metric('email.unsubscribed', $unsubscribed);
    }

    private static function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }
}
