<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Ai\Providers\AnthropicClient;
use App\Ai\Providers\OllamaClient;
use App\Ai\Providers\OpenAiClient;
use App\Ai\Support\AiException;
use App\Ai\Support\AiMessages;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiSummaryClientTest extends TestCase
{
    private function messages(): AiMessages
    {
        return new AiMessages('You summarise reports.', 'Summarise: visitors up 10%.');
    }

    public function test_openai_client_posts_chat_completions_and_returns_text(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Traffic rose 10% this month.']]],
        ])]);

        $client = new OpenAiClient('https://api.openai.com', 'gpt-4o-mini', 'sk-secret');

        $this->assertSame('Traffic rose 10% this month.', $client->complete($this->messages()));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.openai.com/v1/chat/completions'
                && $request['model'] === 'gpt-4o-mini'
                && $request['messages'][0]['role'] === 'system'
                && $request['messages'][1]['role'] === 'user'
                && $request->hasHeader('Authorization', 'Bearer sk-secret');
        });
    }

    public function test_anthropic_client_uses_messages_api_and_version_header(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => 'Uptime held at 99.9%.']],
        ])]);

        $client = new AnthropicClient('https://api.anthropic.com', 'claude-3-5-haiku-latest', 'sk-ant');

        $this->assertSame('Uptime held at 99.9%.', $client->complete($this->messages()));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request['system'] === 'You summarise reports.'
                && $request->hasHeader('x-api-key', 'sk-ant')
                && $request->hasHeader('anthropic-version', '2023-06-01');
        });
    }

    public function test_ollama_client_posts_api_chat_without_auth(): void
    {
        Http::fake(['127.0.0.1:11434/*' => Http::response([
            'message' => ['content' => 'The store grew 5%.'],
        ])]);

        $client = new OllamaClient('http://127.0.0.1:11434', 'llama3.1');

        $this->assertSame('The store grew 5%.', $client->complete($this->messages()));

        Http::assertSent(function ($request): bool {
            return $request->url() === 'http://127.0.0.1:11434/api/chat'
                && $request['stream'] === false
                && ! $request->hasHeader('Authorization');
        });
    }

    public function test_connection_error_throws_a_safe_exception_without_the_key(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 7 to https://api.openai.com?key=sk-secret'));

        $client = new OpenAiClient('https://api.openai.com', 'gpt-4o-mini', 'sk-secret');

        try {
            $client->complete($this->messages());
            $this->fail('Expected AiException.');
        } catch (AiException $e) {
            $this->assertStringNotContainsString('sk-secret', $e->getMessage());
            $this->assertStringContainsString('Could not reach', $e->getMessage());
        }
    }

    public function test_auth_failure_maps_to_a_safe_message(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([], 401)]);
        $client = new OpenAiClient('https://api.openai.com', 'gpt-4o-mini', 'sk-secret');

        $this->expectException(AiException::class);
        $this->expectExceptionMessage('rejected the API key');
        $client->complete($this->messages());
    }

    public function test_rate_limit_maps_to_a_safe_message(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([], 429)]);
        $client = new OpenAiClient('https://api.openai.com', 'gpt-4o-mini', 'sk-secret');

        $this->expectException(AiException::class);
        $this->expectExceptionMessage('rate-limiting');
        $client->complete($this->messages());
    }

    public function test_empty_response_is_treated_as_a_failure(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => '   ']]]])]);

        $client = new OpenAiClient('https://api.openai.com', 'gpt-4o-mini', 'sk-secret');

        $this->expectException(AiException::class);
        $client->complete($this->messages());
    }

    public function test_verify_reports_success(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['choices' => [['message' => ['content' => 'OK']]]])]);
        $client = new OpenAiClient('https://api.openai.com', 'gpt-4o-mini', 'sk-secret');

        $this->assertTrue($client->verify()->ok);
    }

    public function test_verify_reports_failure(): void
    {
        Http::fake(['api.openai.com/*' => Http::response([], 401)]);
        $client = new OpenAiClient('https://api.openai.com', 'gpt-4o-mini', 'sk-secret');

        $result = $client->verify();
        $this->assertFalse($result->ok);
        $this->assertStringContainsString('rejected the API key', $result->message);
    }
}
