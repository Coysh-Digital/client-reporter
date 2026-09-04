<?php

declare(strict_types=1);

namespace App\Mcp\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;

trait AuthorizesStaffAccess
{
    /**
     * Ensure the caller may read agency data.
     *
     * Over HTTP the request is authenticated with a Sanctum token, so a user is
     * always present and must be active agency staff. Over the local (stdio)
     * transport there is no authenticated user — whoever runs the command
     * already has server access, so it is trusted.
     *
     * Returns an error Response to short-circuit the tool, or null to proceed.
     */
    protected function denyUnlessStaff(Request $request): ?Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return null;
        }

        if (! $user->is_active) {
            return Response::error('This account is deactivated.');
        }

        if (! Gate::forUser($user)->allows('access-admin')) {
            return Response::error('This token is not authorised to read agency data.');
        }

        return null;
    }
}
