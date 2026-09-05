<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ReportShare;
use App\Reporting\ReportDocument;
use App\Reporting\ReportShareService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a report over a secure public share link. Nothing here requires an
 * account. Access is gated by the unguessable token, plus optional expiry,
 * revocation and password protection. Password guesses are rate limited per
 * link (route middleware) and a link is revoked outright after too many
 * failures, so a weak password cannot be brute-forced over days.
 */
class PublicReportController
{
    /** Wrong passwords before the link is revoked. */
    private const MAX_FAILED_UNLOCKS = 20;

    /** How long an unlocked link stays unlocked in a visitor's session. */
    private const UNLOCK_TTL_SECONDS = 1800;

    public function show(string $token, ReportShareService $shares, ReportDocument $document): View|Response
    {
        $share = $shares->resolve($token);

        if ($share === null) {
            return response()->view('reports.public-unavailable', [], 404);
        }

        if ($share->requiresPassword() && ! $this->isUnlocked($share)) {
            return view('reports.public-password', ['token' => $token, 'failed' => false]);
        }

        $render = $share->report->latestRender;

        if ($render === null) {
            return response()->view('reports.public-unavailable', [], 404);
        }

        $share->forceFill(['views' => $share->views + 1, 'last_viewed_at' => now()])->save();

        return view('reports.document', $document->fromRender($render));
    }

    public function unlock(string $token, Request $request, ReportShareService $shares): RedirectResponse|View|Response
    {
        $share = $shares->resolve($token);

        if ($share === null) {
            return redirect()->route('public-report', ['token' => $token]);
        }

        $password = (string) $request->input('password');

        if (! $share->requiresPassword() || ! Hash::check($password, (string) $share->password_hash)) {
            $share->forceFill(['failed_unlocks' => $share->failed_unlocks + 1])->save();

            if ($share->failed_unlocks >= self::MAX_FAILED_UNLOCKS) {
                $share->forceFill(['revoked_at' => now()])->save();

                return response()->view('reports.public-unavailable', [], 404);
            }

            return view('reports.public-password', ['token' => $token, 'failed' => true]);
        }

        $share->forceFill(['failed_unlocks' => 0])->save();
        session()->put($this->sessionKey($share), now()->timestamp);

        return redirect()->route('public-report', ['token' => $token]);
    }

    private function isUnlocked(ReportShare $share): bool
    {
        $unlockedAt = (int) session()->get($this->sessionKey($share), 0);

        return $unlockedAt > 0 && now()->timestamp - $unlockedAt <= self::UNLOCK_TTL_SECONDS;
    }

    /**
     * Keyed on the token hash, so a rotated link never inherits an old unlock.
     */
    private function sessionKey(ReportShare $share): string
    {
        return 'report_share_unlocked_'.$share->token_hash;
    }
}
