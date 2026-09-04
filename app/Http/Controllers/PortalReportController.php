<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use App\Reporting\ReportDocument;
use Illuminate\Contracts\View\View;

/**
 * Renders a generated report for the logged-in client, scoped so a client can
 * only ever see reports for their own sites.
 */
class PortalReportController
{
    public function __invoke(Report $report, ReportDocument $document): View
    {
        /** @var User $user */
        $user = auth()->user();

        abort_unless($report->site->client_id === $user->client_id, 403);

        $render = $report->latestRender;
        abort_if($render === null, 404);

        return view('reports.document', $document->fromRender($render));
    }
}
