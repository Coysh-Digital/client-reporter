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
 * revocation and password protection.
 */
class PublicReportController
{
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

        $share->increment('views');
        $share->forceFill(['last_viewed_at' => now()])->save();

        return view('reports.document', $document->fromRender($render));
    }

    public function unlock(string $token, Request $request, ReportShareService $shares): RedirectResponse|View
    {
        $share = $shares->resolve($token);

        if ($share === null) {
            return redirect()->route('public-report', ['token' => $token]);
        }

        $password = (string) $request->input('password');

        if (! $share->requiresPassword() || ! Hash::check($password, (string) $share->password_hash)) {
            return view('reports.public-password', ['token' => $token, 'failed' => true]);
        }

        session()->put($this->sessionKey($share), true);

        return redirect()->route('public-report', ['token' => $token]);
    }

    private function isUnlocked(ReportShare $share): bool
    {
        return (bool) session()->get($this->sessionKey($share), false);
    }

    private function sessionKey(ReportShare $share): string
    {
        return 'report_share_unlocked_'.$share->id;
    }
}
