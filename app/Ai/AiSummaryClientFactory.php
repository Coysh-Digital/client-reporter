<?php

declare(strict_types=1);

namespace App\Ai;

use App\Ai\Contracts\AiSummaryClient;
use App\Ai\Providers\AnthropicClient;
use App\Ai\Providers\OllamaClient;
use App\Ai\Providers\OpenAiClient;
use App\Ai\Support\AiException;
use App\Models\AiSetting;

/**
 * Builds the concrete provider client for a given AI configuration, resolving
 * the base URL and model from the setting with a fall back to config defaults.
 */
class AiSummaryClientFactory
{
    public function make(AiSetting $setting): AiSummaryClient
    {
        $provider = (string) $setting->provider;
        /** @var array<string, mixed> $defaults */
        $defaults = (array) config('services.'.$provider, []);

        $base = rtrim((string) ($setting->base_url ?: ($defaults['base_url'] ?? '')), '/');
        $model = (string) ($setting->model ?: ($defaults['model'] ?? ''));
        $key = (string) ($setting->apiKey() ?? '');

        return match ($provider) {
            'openai' => new OpenAiClient($base, $model, $key),
            'anthropic' => new AnthropicClient($base, $model, $key),
            'ollama' => new OllamaClient($base, $model),
            default => throw new AiException('No AI provider is configured.'),
        };
    }
}
