<?php

declare(strict_types=1);

namespace App\Integrations\Support;

/**
 * How an integration authenticates with its external service.
 *
 * - ApiKey: the user pastes an API key/token, stored encrypted.
 * - OAuth: a redirect-based OAuth flow (e.g. Google Analytics).
 * - ConnectorToken: Client Reporter issues a signed connection code consumed by
 *   a companion plugin (WordPress/Craft), which it then verifies.
 */
enum AuthMethod: string
{
    case ApiKey = 'api_key';
    case OAuth = 'oauth';
    case ConnectorToken = 'connector_token';
}
