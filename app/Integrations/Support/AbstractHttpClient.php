<?php

declare(strict_types=1);

namespace App\Integrations\Support;

use App\Support\Http\OutboundUrl;
use App\Support\Http\UnsafeUrlException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The shared plumbing behind every integration's HTTP client: one place for
 * timeouts, retries on transient failures, an identifiable user-agent, the
 * outbound URL guard for user-supplied hosts, and the translation of transport
 * and HTTP errors into IntegrationException messages that are safe to show
 * agency staff. Subclasses describe the provider and the request; they never
 * touch Http:: directly.
 */
abstract class AbstractHttpClient
{
    protected int $timeout = 20;

    /** Retries after a connection error or 5xx; 0 disables retries. */
    protected int $retries = 2;

    protected int $retryDelayMs = 250;

    /** The provider's name as it should read in error messages. */
    abstract protected function provider(): string;

    /**
     * A base URL the user supplied (self-hosted service, store address). Null
     * for vendor-hosted APIs, whose hosts are fixed constants.
     */
    protected function baseUrl(): ?string
    {
        return null;
    }

    /**
     * Join a path onto the (guarded) base URL. Absolute URLs pass through, so
     * vendor clients can hand over their constant endpoints.
     */
    protected function url(string $path): string
    {
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $base = $this->baseUrl();

        if ($base === null || trim($base) === '') {
            throw new IntegrationException($this->provider().' has no address configured.');
        }

        return rtrim(OutboundUrl::check($base), '/').'/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $query
     * @param  (callable(PendingRequest): PendingRequest)|null  $configure
     */
    protected function get(string $path, array $query = [], ?callable $configure = null): Response
    {
        // Passing an (even empty) query array to Laravel's get() sets Guzzle's
        // query option, which REPLACES any query string already on the URL. So
        // when no query array is given, call get() with the URL alone — this
        // preserves query strings a client built into the path itself (e.g.
        // PageSpeed's repeated `category` params, which can't be expressed as a
        // plain array).
        return $this->send(fn (PendingRequest $request): Response => $query === []
            ? $this->configure($request, $configure)->get($this->url($path))
            : $this->configure($request, $configure)->get($this->url($path), $query));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  (callable(PendingRequest): PendingRequest)|null  $configure
     */
    protected function post(string $path, array $data = [], bool $asForm = false, ?callable $configure = null): Response
    {
        return $this->send(function (PendingRequest $request) use ($path, $data, $asForm, $configure): Response {
            $request = $this->configure($request, $configure);

            return ($asForm ? $request->asForm() : $request)->post($this->url($path), $data);
        });
    }

    /**
     * Run a request, translating transport-level failures into safe messages.
     *
     * @param  callable(PendingRequest): Response  $do
     */
    protected function send(callable $do): Response
    {
        try {
            return $do($this->http());
        } catch (UnsafeUrlException $e) {
            throw new IntegrationException($e->getMessage());
        } catch (ConnectionException) {
            throw new IntegrationException($this->unreachableMessage());
        }
    }

    /**
     * Turn a failed response into the right exception. Provide `$messages`
     * keyed by status code to keep provider-specific wording; the defaults
     * cover the rest. 401/403 and 429 raise their own types so collection can
     * mark the connection as needing re-authentication or as rate limited.
     *
     * @param  array<int, string>  $messages
     */
    protected function guard(Response $response, array $messages = []): Response
    {
        if ($response->successful()) {
            return $response;
        }

        $status = $response->status();
        $message = $messages[$status] ?? match (true) {
            $status === 401 || $status === 403 => $this->provider().' rejected the credentials. Check them and reconnect.',
            $status === 404 => $this->provider().' was not found at this address. Check the URL.',
            $status === 429 => $this->provider().' is rate-limiting requests. Try again shortly.',
            default => $this->provider().' returned an error (HTTP '.$status.').',
        };

        throw match (true) {
            $status === 401 || $status === 403 => new AuthenticationException($message),
            $status === 429 => new RateLimitedException($message),
            default => new IntegrationException($message),
        };
    }

    /**
     * The decoded JSON body, or an exception when it is not an object/array.
     *
     * @return array<mixed>
     */
    protected function json(Response $response): array
    {
        $data = $response->json();

        if (! is_array($data)) {
            throw new IntegrationException($this->provider().' returned an unexpected response.');
        }

        return $data;
    }

    protected function unreachableMessage(): string
    {
        return 'Could not reach '.$this->provider().'. Please try again shortly.';
    }

    protected function http(): PendingRequest
    {
        $request = $this->baseUrl() !== null
            ? app(OutboundUrl::class)->client($this->timeout)
            : Http::timeout($this->timeout)->withUserAgent(OutboundUrl::userAgent());

        $request = $request->acceptJson();

        if ($this->retries > 0) {
            $request = $request->retry(
                $this->retries + 1,
                $this->retryDelayMs,
                fn (Throwable $e): bool => $e instanceof ConnectionException
                    || ($e instanceof RequestException && $e->response->serverError()),
                throw: false,
            );
        }

        return $request;
    }

    /**
     * @param  (callable(PendingRequest): PendingRequest)|null  $configure
     */
    private function configure(PendingRequest $request, ?callable $configure): PendingRequest
    {
        return $configure !== null ? $configure($request) : $request;
    }
}
