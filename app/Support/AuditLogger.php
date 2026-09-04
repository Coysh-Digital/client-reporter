<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Records security-relevant events (authentication, user management, connection
 * and credential changes) to the audit_logs table. Never log secrets: pass only
 * non-sensitive context in $metadata.
 */
class AuditLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(string $event, ?Model $subject = null, ?User $user = null, array $metadata = []): AuditLog
    {
        $user ??= $this->request->user();

        return AuditLog::query()->create([
            'user_id' => $user?->getKey(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => $this->request->ip(),
            'user_agent' => substr((string) $this->request->userAgent(), 0, 500),
            'metadata' => $metadata ?: null,
        ]);
    }
}
