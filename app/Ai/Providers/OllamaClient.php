<?php

declare(strict_types=1);

namespace App\Ai\Providers;

use App\Ai\Support\AiException;
use App\Ai\Support\AiMessages;
use App\Integrations\Support\VerificationResult;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Ollama client for a locally- or self-hosted model. No authentication.
 */
class OllamaClient extends AbstractAiClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $model,
    ) {}

    public function complete(AiMessages $messages): string
    {
        try {
            $response = Http::timeout(30)
                ->acceptJson()
                ->post($this->baseUrl.'/api/chat', [
                    'model' => $this->model,
                    'messages' => [
                        ['role' => 'system', 'content' => $messages->system],
                        ['role' => 'user', 'content' => $messages->user],
                    ],
                    'stream' => false,
                ]);
        } catch (ConnectionException) {
            throw new AiException('Could not reach Ollama at the configured address.');
        }

        $this->guard($response);

        return $this->text($response->json('message.content'));
    }

    public function verify(): VerificationResult
    {
        try {
            $response = Http::timeout(15)->acceptJson()->get($this->baseUrl.'/api/tags');
        } catch (ConnectionException) {
            return VerificationResult::failure('Could not reach Ollama at the configured address.');
        }

        if (! $response->successful()) {
            return VerificationResult::failure('Ollama returned an error (HTTP '.$response->status().').');
        }

        return VerificationResult::success('Connected to Ollama ('.$this->model.').');
    }
}
