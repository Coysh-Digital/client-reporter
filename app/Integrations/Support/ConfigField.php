<?php

declare(strict_types=1);

namespace App\Integrations\Support;

/**
 * A single field in an integration's connection/setup form. Fields flagged
 * `secret` are stored in the encrypted credentials bag; the rest are stored as
 * plain settings. The generic connection form renders these without the
 * integration needing to ship its own Blade.
 */
readonly class ConfigField
{
    /**
     * @param  array<string, string>  $options  key => label, for select fields
     * @param  array<int, string>  $rules  additional Laravel validation rules
     * @param  string  $scope  'account' fields (API keys, tokens, base URLs) can
     *                         be stored once on a workspace connection; 'site' fields (which monitor
     *                         or property maps to this site) are always per-site.
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'text',
        public bool $required = true,
        public bool $secret = false,
        public ?string $help = null,
        public ?string $placeholder = null,
        public array $options = [],
        public array $rules = [],
        public string $scope = 'account',
    ) {}

    public static function apiKey(string $key = 'api_key', string $label = 'API key', ?string $help = null): self
    {
        return new self($key, $label, type: 'password', secret: true, help: $help);
    }

    public static function text(string $key, string $label, bool $required = true, ?string $help = null): self
    {
        return new self($key, $label, required: $required, help: $help);
    }

    public static function select(string $key, string $label, array $options, bool $required = true): self
    {
        return new self($key, $label, type: 'select', required: $required, options: $options);
    }

    /**
     * Laravel validation rules for this field.
     *
     * @return array<int, string>
     */
    public function validationRules(): array
    {
        $rules = [$this->required ? 'required' : 'nullable'];

        $rules[] = match ($this->type) {
            'url' => 'url',
            'number' => 'numeric',
            'select' => 'in:'.implode(',', array_keys($this->options)),
            default => 'string',
        };

        return array_merge($rules, $this->rules);
    }
}
