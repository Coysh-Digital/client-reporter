<?php

declare(strict_types=1);

namespace App\Ai\Providers;

use App\Ai\Contracts\AiSummaryClient;
use App\Ai\Support\AiException;
use Illuminate\Http\Client\Response;

/**
 * Shared HTTP-failure handling for the provider clients. Response bodies and
 * credentials are never placed into the exception message — only a safe,
 * status-derived explanation — so nothing sensitive can leak into logs or the UI.
 */
abstract class AbstractAiClient implements AiSummaryClient
{
    /**
     * Raise a safe exception for a non-successful response.
     *
     * @throws AiException
     */
    protected function guard(Response $response): void
    {
        if ($response->successful()) {
            return;
        }

        throw new AiException(match ($response->status()) {
            401, 403 => 'The AI provider rejected the API key. Check the key in Settings.',
            404 => 'The AI provider could not find the requested model. Check the model name.',
            429 => 'The AI provider is rate-limiting requests. Try again shortly.',
            default => 'The AI provider returned an error (HTTP '.$response->status().').',
        });
    }

    /**
     * Trim and validate provider text, or fail safely on an empty response.
     *
     * @throws AiException
     */
    protected function text(mixed $value): string
    {
        if (! is_string($value) || trim($value) === '') {
            throw new AiException('The AI provider returned an empty response.');
        }

        return trim($value);
    }
}
