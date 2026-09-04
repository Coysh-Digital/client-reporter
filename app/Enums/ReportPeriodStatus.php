<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Where a site's report for a given period stands, from the agency's point of
 * view. Reports themselves only track draft → final; "ready" vs "sent" is
 * derived from whether a share link / email exists (ReportStatusResolver).
 */
enum ReportPeriodStatus: string
{
    case NotStarted = 'not_started';
    case Draft = 'draft';
    case Ready = 'ready';
    case Sent = 'sent';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not started',
            self::Draft => 'Draft',
            self::Ready => 'Ready',
            self::Sent => 'Sent',
        };
    }

    /**
     * Maps to the <x-badge> / <x-status-dot> variant palette.
     */
    public function badge(): string
    {
        return match ($this) {
            self::NotStarted => 'warn',
            self::Draft => 'neutral',
            self::Ready => 'info',
            self::Sent => 'ok',
        };
    }

    /**
     * The contextual call-to-action label for this state.
     */
    public function actionLabel(): string
    {
        return match ($this) {
            self::NotStarted => 'Create',
            self::Draft => 'Continue',
            self::Ready => 'Send',
            self::Sent => 'View',
        };
    }
}
