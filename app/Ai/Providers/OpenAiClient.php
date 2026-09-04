<?php

declare(strict_types=1);

namespace App\Ai\Providers;

use App\Ai\Support\AiException;
use App\Ai\Support\AiMessages;
use App\Integrations\Support\VerificationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * OpenAI (and OpenAI-compatible) chat-completions client.
 */
class OpenAiClient extends AbstractAiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
        private readonly string $apiKey,
    ) {}

    public function complete(AiMessages $messages): string
    {
        try {
            $response = Http::withToken($this->apiKey)
                ->timeout(30)
                ->acceptJson()
                ->post($this->baseUrl.'/v1/chat/completions', [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $messages->system],
                        ['role' => 'user', 'content' => $messages->user],
                    ],
                    'temperature' => 0.4,
                    'max_tokens' => 320,
                ]);
        } catch (ConnectionException) {
            throw new AiException('Could not reach OpenAI. Check the connection and try again.');
        }

        $this->guard($response);

        return $this->text($response->json('choices.0.message.content'));
    }

    public function verify(): VerificationResult
    {
        try {
            $this->complete(new AiMessages('You are a connection test.', 'Reply with the single word OK.'));
        } catch (AiException $e) {
            return VerificationResult::failure($e->getMessage());
        }

        return VerificationResult::success('Connected to OpenAI ('.$this->model.').');
    }
}
