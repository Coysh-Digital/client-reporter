<?php

declare(strict_types=1);

namespace App\Reporting\Support;

/**
 * A configurable option a report block exposes in the builder. The chosen value
 * is stored in the block's `config` JSON and read back in resolve() via
 * ReportBlock::configValue(). Kept deliberately small — toggles, numbers and
 * (multi-)selects cover every current block.
 */
readonly class BlockOption
{
    /**
     * @param  'toggle'|'number'|'select'|'multiselect'  $type
     * @param  array<string, string>  $choices  value => label (select/multiselect)
     */
    public function __construct(
        public string $key,
        public string $label,
        public string $type,
        public mixed $default = null,
        public array $choices = [],
        public ?int $min = null,
        public ?int $max = null,
        public ?string $help = null,
    ) {}

    public static function toggle(string $key, string $label, bool $default = true, ?string $help = null): self
    {
        return new self($key, $label, 'toggle', $default, help: $help);
    }

    public static function number(string $key, string $label, int $default, int $min = 1, int $max = 100, ?string $help = null): self
    {
        return new self($key, $label, 'number', $default, min: $min, max: $max, help: $help);
    }

    /**
     * @param  array<string, string>  $choices
     */
    public static function select(string $key, string $label, array $choices, string $default, ?string $help = null): self
    {
        return new self($key, $label, 'select', $default, choices: $choices, help: $help);
    }

    /**
     * @param  array<string, string>  $choices
     * @param  array<int, string>  $default
     */
    public static function multiselect(string $key, string $label, array $choices, array $default, ?string $help = null): self
    {
        return new self($key, $label, 'multiselect', $default, choices: $choices, help: $help);
    }
}
