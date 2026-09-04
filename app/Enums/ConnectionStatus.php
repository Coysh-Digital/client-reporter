<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The health of a site's connection to an integration, surfaced in the
 * integrations UI with clear, client-friendly language.
 */
enum ConnectionStatus: string
{
    case NotConnected = 'not_connected';
    case Connected = 'connected';
    case NeedsAttention = 'needs_attention';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::NotConnected => 'Not connected',
            self::Connected => 'Connected',
            self::NeedsAttention => 'Needs attention',
            self::Error => 'Error',
        };
    }

    /**
     * Maps to the <x-badge> variant palette.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Connected => 'ok',
            self::NeedsAttention => 'warn',
            self::Error => 'danger',
            self::NotConnected => 'neutral',
        };
    }

    public function isLive(): bool
    {
        return $this === self::Connected || $this === self::NeedsAttention;
    }
}
