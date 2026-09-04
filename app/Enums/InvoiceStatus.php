<?php

declare(strict_types=1);

namespace App\Enums;

use App\Models\Invoice;

/**
 * The lifecycle of an agency-raised invoice. "Overdue" is not a stored state —
 * it's derived from a Sent invoice whose due date has passed, so it can never
 * go stale (see {@see Invoice::isOverdue()}).
 */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Void = 'void';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Sent => 'Sent',
            self::Paid => 'Paid',
            self::Void => 'Void',
        };
    }

    /**
     * Maps to the <x-badge> variant palette.
     */
    public function badge(): string
    {
        return match ($this) {
            self::Draft => 'neutral',
            self::Sent => 'info',
            self::Paid => 'ok',
            self::Void => 'neutral',
        };
    }
}
