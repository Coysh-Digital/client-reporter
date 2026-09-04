<?php

declare(strict_types=1);

namespace Tests\Feature\Search;

use App\Livewire\CommandPalette;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommandPaletteTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finds_a_client_by_name(): void
    {
        $admin = User::factory()->administrator()->create();
        Client::factory()->create(['name' => 'Northwind Cafe']);

        Livewire::actingAs($admin)->test(CommandPalette::class)
            ->set('q', 'Northwind')
            ->assertSee('Northwind Cafe');
    }

    public function test_a_short_query_returns_nothing(): void
    {
        $admin = User::factory()->administrator()->create();
        Client::factory()->create(['name' => 'Northwind Cafe']);

        Livewire::actingAs($admin)->test(CommandPalette::class)
            ->set('q', 'N')
            ->assertDontSee('Northwind Cafe');
    }
}
