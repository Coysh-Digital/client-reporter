<?php

declare(strict_types=1);

namespace App\Reporting;

use App\Integrations\IntegrationRegistry;
use App\Reporting\Contracts\BlockType;
use InvalidArgumentException;

/**
 * Holds every available report block: the core blocks plus the blocks each
 * installed integration registers via Integration::reportBlocks().
 */
class BlockTypeRegistry
{
    /** @var array<int, class-string<BlockType>> */
    private array $classes = [];

    /** @var array<string, BlockType>|null */
    private ?array $resolved = null;

    /**
     * @param  array<int, class-string<BlockType>>  $coreClasses
     */
    public function __construct(array $coreClasses, private readonly IntegrationRegistry $integrations)
    {
        foreach ($coreClasses as $class) {
            $this->register($class);
        }
    }

    /**
     * @param  class-string<BlockType>  $class
     */
    public function register(string $class): void
    {
        if (! is_subclass_of($class, BlockType::class)) {
            throw new InvalidArgumentException("[{$class}] must extend ".BlockType::class.'.');
        }

        if (! in_array($class, $this->classes, true)) {
            $this->classes[] = $class;
            $this->resolved = null;
        }
    }

    /**
     * @return array<string, BlockType>
     */
    public function all(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $classes = $this->classes;

        foreach ($this->integrations->all() as $integration) {
            foreach ($integration->reportBlocks() as $blockClass) {
                if (! in_array($blockClass, $classes, true)) {
                    $classes[] = $blockClass;
                }
            }
        }

        $resolved = [];
        foreach ($classes as $class) {
            /** @var BlockType $instance */
            $instance = app($class);
            $resolved[$instance->type()] = $instance;
        }

        return $this->resolved = $resolved;
    }

    public function find(string $type): ?BlockType
    {
        return $this->all()[$type] ?? null;
    }

    public function has(string $type): bool
    {
        return isset($this->all()[$type]);
    }

    /**
     * Blocks grouped by their menu group, preserving insertion order.
     *
     * @return array<string, array<int, BlockType>>
     */
    public function grouped(): array
    {
        $groups = [];

        foreach ($this->all() as $block) {
            $groups[$block->group()][] = $block;
        }

        return $groups;
    }
}
