<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ReportShare;
use App\Reporting\ReportDocument;
use App\Reporting\ReportPdf;
use App\Reporting\ReportShareService;
use App\Support\Branding\BrandingResolver;
use App\Support\Branding\ResolvedBranding;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\LaravelPdf\PdfBuilder;
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
            return $this->unavailable(null);
        }

        if ($share->requiresPassword() && ! $this->isUnlocked($share)) {
            return view('reports.public-password', ['token' => $token, 'failed' => false, 'branding' => $this->brandingFor($share)]);
        }

        $render = $share->report->latestRender;

        if ($render === null) {
            return $this->unavailable($share);
        }

        // Count only the recipient's opens, not an agency user previewing their
        // own link, so the open count is a trustworthy "did the client see it".
        if (! auth()->check()) {
            $share->forceFill(['views' => $share->views + 1, 'last_viewed_at' => now()])->save();
        }

        return view('reports.document', $document->fromRender($render) + [
            'pdfUrl' => route('public-report.pdf', ['token' => $token]),
        ]);
    }

    /**
     * The same report as a downloadable PDF, gated by the same token (and
     * password) as the web view.
     */
    public function pdf(string $token, ReportShareService $shares, ReportPdf $pdf): Response|PdfBuilder|RedirectResponse
    {
        $share = $shares->resolve($token);

        if ($share === null) {
            return $this->unavailable(null);
        }

        if ($share->requiresPassword() && ! $this->isUnlocked($share)) {
            return redirect()->route('public-report', ['token' => $token]);
        }

        if ($share->report->latestRender === null) {
            return $this->unavailable($share);
        }

        return $pdf->download($share->report) ?? $this->unavailable($share);
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

                return $this->unavailable($share);
            }

            return view('reports.public-password', ['token' => $token, 'failed' => true, 'branding' => $this->brandingFor($share)]);
        }

        $share->forceFill(['failed_unlocks' => 0])->save();
        session()->put($this->sessionKey($share), now()->timestamp);

        return redirect()->route('public-report', ['token' => $token]);
    }

    /**
     * The gate pages wear the agency's branding for the report's site when the
     * link is known, and the agency default otherwise — never the product's.
     */
    private function brandingFor(?ReportShare $share): ResolvedBranding
    {
        $resolver = app(BrandingResolver::class);
        $site = $share?->report?->site;

        return $site !== null ? $resolver->forSite($site) : $resolver->resolve([$resolver->global()]);
    }

    private function unavailable(?ReportShare $share): Response
    {
        return response()->view('reports.public-unavailable', ['branding' => $this->brandingFor($share)], 404);
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
