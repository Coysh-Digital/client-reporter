<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Models\Report;
use App\Models\ReportShare;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Issues and resolves secure public share links. Tokens are random and stored
 * only as a SHA-256 hash; the plaintext is returned once at creation and never
 * persisted, so a database read cannot reveal a working link.
 */
class ReportShareService
{
    /**
     * @return array{share: ReportShare, token: string}
     */
    public function create(Report $report, ?int $expiresInDays = null, ?string $password = null): array
    {
        $token = Str::random((int) config('client-reporter.reports.share_token_bytes', 32) * 2);

        $share = $report->shares()->create([
            'token_hash' => $this->hash($token),
            'password_hash' => $password !== null && $password !== '' ? Hash::make($password) : null,
            'expires_at' => $expiresInDays !== null ? now()->addDays($expiresInDays) : null,
            'created_by' => auth()->id(),
        ]);

        return ['share' => $share, 'token' => $token];
    }

    /**
     * Resolve an active share from a plaintext token, or null.
     */
    public function resolve(string $token): ?ReportShare
    {
        $share = ReportShare::query()
            ->where('token_hash', $this->hash($token))
            ->first();

        if ($share === null || ! $share->isActive()) {
            return null;
        }

        return $share;
    }

    public function url(string $token): string
    {
        return route('public-report', ['token' => $token]);
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
