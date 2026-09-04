<?php

declare(strict_types=1);

namespace App\Reporting\Contracts;

use App\Integrations\IntegrationRegistry;
use App\Integrations\Support\IntegrationCategory;
use App\Models\Site;
use App\Reporting\Support\BlockContext;
use App\Reporting\Support\BlockOption;
use App\Support\ReportIcons;

/**
 * Defines a kind of report block: how it appears in the builder, what data it
 * resolves for a period, and which Blade view renders it. Core blocks and
 * integration-provided blocks share this contract, so an integration can add
 * report blocks without touching the reporting engine.
 */
abstract class BlockType
{
    /**
     * Stable, dotted type key (e.g. "text", "uptime.summary").
     */
    abstract public function type(): string;

    abstract public function label(): string;

    public function description(): string
    {
        return '';
    }

    /**
     * Grouping shown in the "add block" menu.
     */
    public function group(): string
    {
        return 'General';
    }

    /**
     * Icon key shown next to this block's heading and in the report's
     * Contents section — one of the keys in {@see ReportIcons::KEYS}.
     */
    public function icon(): string
    {
        return 'document';
    }

    /**
     * If set, the block only produces data when the site has a live connection
     * to this integration; the builder warns when it is missing.
     */
    public function requiresIntegration(): ?string
    {
        return null;
    }

    /**
     * If set, the block needs any connected integration in this category (e.g.
     * an analytics provider — GA4, Plausible or Fathom). The builder warns when
     * none is connected.
     */
    public function requiresCategory(): ?IntegrationCategory
    {
        return null;
    }

    /**
     * Per-site availability override. Return null to fall back to the
     * requiresIntegration()/requiresCategory() rules; return true/false to
     * decide directly. Used by blocks whose data source is chosen dynamically
     * (e.g. the store block, which can read WooCommerce, Craft Commerce or a
     * standalone Shopify connection).
     */
    public function availableForSite(Site $site): ?bool
    {
        return null;
    }

    /**
     * The integration keys this block needs collected for a site before a
     * report is generated. Defaults to the declared requiresIntegration() /
     * requiresCategory() rules; override when the source is resolved per site.
     *
     * @return array<int, string>
     */
    public function neededIntegrationKeys(Site $site): array
    {
        $keys = [];

        if ($key = $this->requiresIntegration()) {
            $keys[] = $key;
        }

        if ($category = $this->requiresCategory()) {
            $keys = array_merge($keys, app(IntegrationRegistry::class)->keysInCategory($category));
        }

        return array_values(array_unique($keys));
    }

    /**
     * Whether staff commentary is meaningful for this block.
     */
    public function supportsCommentary(): bool
    {
        return true;
    }

    /**
     * Whether an optional AI-written summary can be produced for this block.
     * AI-capable blocks override this and {@see aiFacts()}, and expose an
     * `ai_summary` toggle in their options().
     */
    public function supportsAiSummary(): bool
    {
        return false;
    }

    /**
     * The editable default instruction sent to the AI for this block's summary
     * (or the report-level roundup). Null when the block has no AI summary.
     */
    public function defaultAiPrompt(): ?string
    {
        return null;
    }

    /**
     * A small, whitelisted set of already-resolved figures for the AI to
     * summarise. Only this is ever sent to the provider — never the full
     * resolve() output — so the prompt stays small and leaks nothing beyond the
     * intended numbers. Return an empty array when there is nothing to summarise.
     *
     * @param  array<string, mixed>  $resolved  this block's resolve() output
     * @return array<string, mixed>
     */
    public function aiFacts(array $resolved): array
    {
        return [];
    }

    /**
     * The configurable options this block exposes in the builder. Override to
     * let staff tune the block (row limits, which metrics to show, comparison…).
     *
     * @return array<int, BlockOption>
     */
    public function options(): array
    {
        return [];
    }

    /**
     * Default per-block configuration when the block is added — derived from the
     * declared options() so a block only states its options in one place.
     *
     * @return array<string, mixed>
     */
    public function defaultConfig(): array
    {
        $config = [];
        foreach ($this->options() as $option) {
            $config[$option->key] = $option->default;
        }

        return $config;
    }

    /**
     * Resolve the block's view data for the given context. Must be pure and
     * read only from stored metrics/snapshots via the context reader.
     *
     * @return array<string, mixed>
     */
    abstract public function resolve(BlockContext $context): array;

    /**
     * The Blade view that renders this block on web/PDF reports.
     */
    public function view(): string
    {
        return 'reports.blocks.'.$this->type();
    }

    /**
     * Coerce raw (form) config values into clean, valid values per this block's
     * declared options — dropping anything unknown. Shared by the report builder
     * and the template editor so stored config is always well-formed.
     *
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normaliseConfig(array $config): array
    {
        $out = [];
        foreach ($this->options() as $option) {
            $value = $config[$option->key] ?? $option->default;

            $out[$option->key] = match ($option->type) {
                'toggle' => (bool) $value,
                'number' => max($option->min ?? 1, min($option->max ?? 100, (int) $value)),
                'select' => array_key_exists((string) $value, $option->choices) ? (string) $value : $option->default,
                'multiselect' => array_values(array_intersect(
                    array_map('strval', is_array($value) ? $value : []),
                    array_keys($option->choices),
                )),
            };
        }

        return $out;
    }
}
