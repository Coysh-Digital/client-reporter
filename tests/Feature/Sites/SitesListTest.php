<?php

declare(strict_types=1);

namespace Tests\Feature\Sites;

use App\Livewire\Sites\Index;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Livewire;
use Tests\TestCase;

class SitesListTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_paginates_at_fifteen_per_page_by_default(): void
    {
        Site::factory()->count(20)->create();

        Livewire::actingAs(User::factory()->manager()->create())
            ->test(Index::class)
            ->assertViewHas('sites', fn (LengthAwarePaginator $sites): bool => $sites->perPage() === 15 && $sites->count() === 15);
    }

    public function test_the_per_page_control_shows_more_results(): void
    {
        Site::factory()->count(20)->create();

        Livewire::actingAs(User::factory()->manager()->create())
            ->test(Index::class)
            ->set('perPage', 30)
            ->assertViewHas('sites', fn (LengthAwarePaginator $sites): bool => $sites->perPage() === 30 && $sites->count() === 20);
    }

    public function test_an_out_of_range_per_page_falls_back_to_the_default(): void
    {
        Site::factory()->count(20)->create();

        // A tampered ?perPage= URL value must not bypass the offered options.
        Livewire::actingAs(User::factory()->manager()->create())
            ->test(Index::class)
            ->set('perPage', 9999)
            ->assertViewHas('sites', fn (LengthAwarePaginator $sites): bool => $sites->perPage() === 15);
    }
}
