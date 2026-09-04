<?php

declare(strict_types=1);

namespace App\Ai\Providers;

use App\Ai\Support\AiException;
use App\Ai\Support\AiMessages;
use App\Integrations\Support\VerificationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Anthropic (Claude) Messages API client.
 */
class AnthropicClient extends AbstractAiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly string $apiKey,
    ) {}

    public function complete(AiMessages $messages): string
    {
        try {
            $response = Http::withHeaders([
                'x-api-key' => $this->apiKey,
                'anthropic-version' => '2023-06-01',
            ])
                ->timeout(30)
                ->acceptJson()
                ->post($this->baseUrl.'/v1/messages', [
                    'model' => $this->model,
                    'max_tokens' => 320,
                    'system' => $messages->system,
                    'messages' => [
                        ['role' => 'user', 'content' => $messages->user],
                    ],
                ]);
        } catch (ConnectionException) {
            throw new AiException('Could not reach Anthropic. Check the connection and try again.');
        }

        $this->guard($response);

        return $this->text($response->json('content.0.text'));
    }

    public function verify(): VerificationResult
    {
        try {
            $this->complete(new AiMessages('You are a connection test.', 'Reply with the single word OK.'));
        } catch (AiException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success('Connected to Anthropic ('.$this->model.').');
    }
}
