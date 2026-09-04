<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * The single, workspace-wide AI provider configuration used to write optional
 * report summaries. The API key lives in an encrypted `credentials` bag (never
 * the plaintext settings table) and is excluded from serialisation, mirroring
 * {@see WorkspaceIntegration}. Editable prompt text and the global tone live in
 * the plaintext Settings repository instead, since they are not secret.
 *
 * @property string|null $provider 'openai' | 'anthropic' | 'ollama'
 * @property string|null $model
 * @property string|null $base_url
 * @property bool $enabled
 * @property array<string, mixed>|null $credentials
 */
class AiSetting extends Model
{
    protected $fillable = [
        'provider',
        'model',
        'base_url',
        'enabled',
        'credentials',
    ];

    protected $hidden = ['credentials'];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    /**
     * The single configuration row (unsaved when never configured).
     */
    public static function current(): self
    {
        return static::query()->firstOrNew([]);
    }

    /**
     * The stored API key, or null. Never surfaced to the browser.
     */
    public function apiKey(): ?string
    {
        $key = ($this->credentials ?? [])['api_key'] ?? null;

        return is_string($key) && $key !== '' ? $key : null;
    }

    /**
     * Whether summaries can actually be produced: enabled, a provider chosen,
     * and (for the hosted providers) a key present. Ollama needs no key.
     */
    public function isUsable(): bool
    {
        if (! $this->enabled || $this->provider === null) {
            return false;
        }

        return $this->provider === 'ollama' || $this->apiKey() !== null;
    }
}
