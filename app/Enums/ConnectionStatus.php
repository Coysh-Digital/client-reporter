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
    case AuthExpired = 'auth_expired';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::NotConnected => 'Not connected',
            self::Connected => 'Connected',
            self::NeedsAttention => 'Needs attention',
            self::AuthExpired => 'Authentication expired',
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
            self::AuthExpired, self::Error => 'danger',
            self::NotConnected => 'neutral',
        };
    }

    /**
     * Whether scheduled collection should keep trying this connection. An
     * expired authentication stops until someone reconnects: hammering a dead
     * token only burns the provider's goodwill.
     */
    public function isLive(): bool
    {
        return $this === self::Connected || $this === self::NeedsAttention;
    }

    /**
     * Whether the connection needs a person to do something.
     */
    public function needsAttention(): bool
    {
        return $this === self::NeedsAttention || $this === self::AuthExpired || $this === self::Error;
    }

    /**
     * @return array<int, string>
     */
    public static function liveValues(): array
    {
        return [self::Connected->value, self::NeedsAttention->value];
    }

    /**
     * @return array<int, string>
     */
    public static function troubledValues(): array
    {
        return [self::NeedsAttention->value, self::AuthExpired->value, self::Error->value];
    }
}
