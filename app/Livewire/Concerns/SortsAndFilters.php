<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Search and sortable columns for a paginated list. The component declares
 * which public keys map to which columns (`sortable()`), optionally which
 * columns a search matches (`searchable()`, "relation.column" allowed), and
 * calls `applySortAndSearch()` on its query. Everything else — URL binding,
 * direction toggling, whitelisting, page reset — lives here. Components using
 * it also use Livewire's WithPagination.
 */
trait SortsAndFilters
{
    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $sort = '';

    #[Url]
    public string $direction = 'asc';

    /**
     * Public key => column (or "relation.column" for a belongs-to relation).
     *
     * @return array<string, string>
     */
    abstract protected function sortable(): array;

    /**
     * Columns (or "relation.column") a search term is matched against.
     *
     * @return array<int, string>
     */
    protected function searchable(): array
    {
        return [];
    }

    /**
     * @return array{0: string, 1: string} key and direction
     */
    protected function defaultSort(): array
    {
        return [(string) array_key_first($this->sortable()), 'asc'];
    }

    public function sortBy(string $key): void
    {
        if (! array_key_exists($key, $this->sortable())) {
            return;
        }

        if ($this->sort === $key) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $key;
            $this->direction = 'asc';
        }

        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    /**
     * The sort key currently in effect (the URL value when valid, else the default).
     */
    public function currentSort(): string
    {
        return array_key_exists($this->sort, $this->sortable()) ? $this->sort : $this->defaultSort()[0];
    }

    public function currentDirection(): string
    {
        if (! array_key_exists($this->sort, $this->sortable())) {
            return $this->defaultSort()[1];
        }

        return $this->direction === 'desc' ? 'desc' : 'asc';
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySortAndSearch(Builder $query): Builder
    {
        $term = trim($this->search);

        if ($term !== '' && $this->searchable() !== []) {
            $query->where(function (Builder $q) use ($term): void {
                foreach ($this->searchable() as $column) {
                    if (str_contains($column, '.')) {
                        [$relation, $field] = explode('.', $column, 2);
                        $q->orWhereHas($relation, fn (Builder $r) => $r->where($field, 'like', "%{$term}%"));
                    } else {
                        $q->orWhere($column, 'like', "%{$term}%");
                    }
                }
            });
        }

        $column = $this->sortable()[$this->currentSort()];
        $direction = $this->currentDirection();

        if (str_contains($column, '.')) {
            [$relation, $field] = explode('.', $column, 2);
            $related = $query->getModel()->{$relation}();
            $relatedTable = $related->getRelated()->getTable();
            $baseTable = $query->getModel()->getTable();

            $query->orderBy(
                $related->getRelated()->newQuery()
                    ->select($field)
                    ->whereColumn("{$relatedTable}.id", "{$baseTable}.{$related->getForeignKeyName()}")
                    ->limit(1),
                $direction,
            );
        } else {
            $query->orderBy($column, $direction);
        }

        return $query;
    }
}
