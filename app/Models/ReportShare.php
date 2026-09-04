<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A secure, revocable public share link for a report. The token is stored only
 * as a hash; the plaintext is shown to the agency once at creation.
 *
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
 * @property string|null $password_hash
 */
class ReportShare extends Model
{
    protected $fillable = [
        'report_id',
        'token_hash',
        'password_hash',
        'expires_at',
        'revoked_at',
        'views',
        'last_viewed_at',
        'created_by',
    ];

    protected $hidden = ['token_hash', 'password_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'views' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isActive(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function requiresPassword(): bool
    {
        return $this->password_hash !== null;
    }
}
