<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * The shared Blade components are the accessibility contract for every screen:
 * a change here is a change everywhere, so their ARIA output is pinned.
 */
class DesignSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_segmented_control_marks_the_selected_option_pressed(): void
    {
        $html = $this->blade(
            '<x-segmented :options="[\'all\' => \'All\', \'active\' => \'Active\']" value="active" action="setStatus" label="Filter clients" />'
        );

        $html->assertSee('role="group"', false)
            ->assertSee('aria-label="Filter clients"', false)
            ->assertSee('aria-pressed="true"', false)
            ->assertSee('aria-pressed="false"', false)
            ->assertSee('wire:click="setStatus(\'active\')"', false);
    }

    public function test_dropdown_renders_a_menu_with_menu_items(): void
    {
        $html = $this->blade(<<<'BLADE'
            <x-dropdown>
                <x-slot:trigger><button type="button">Open</button></x-slot:trigger>
                <x-dropdown-item href="/clients" icon="building-user">Clients</x-dropdown-item>
                <x-dropdown-item danger>Delete</x-dropdown-item>
            </x-dropdown>
        BLADE);

        $html->assertSee('aria-haspopup="menu"', false)
            ->assertSee('role="menu"', false)
            ->assertSee('role="menuitem"', false)
            ->assertSee('cr-menu-item-danger', false)
            ->assertSee('x-cloak', false);
    }

    public function test_dialog_and_confirm_dialog_use_native_dialogs_with_labels(): void
    {
        $this->blade('<x-dialog name="add-site" title="Add a site" description="Pick a client.">Body</x-dialog>')
            ->assertSee('<dialog', false)
            ->assertSee('aria-labelledby=', false)
            ->assertSee('aria-describedby=', false)
            ->assertSee('open-add-site', false);

        $this->blade('<x-confirm-dialog />')
            ->assertSee('<dialog', false)
            ->assertSee('role="alertdialog"', false);

        $this->blade('<x-confirm-button action="delete(3)" title="Delete?" message="Gone." confirm="Delete" danger>Delete</x-confirm-button>')
            ->assertSee("\$dispatch('confirm'", false)
            ->assertSee('$wire.delete(3)', false);
    }

    public function test_checkbox_and_toggle_are_real_inputs(): void
    {
        $this->blade('<x-checkbox wire:model="active" id="active" label="Active" help="Shown on the dashboard." />')
            ->assertSee('type="checkbox"', false)
            ->assertSee('wire:model="active"', false)
            ->assertSee('Shown on the dashboard.');

        $this->blade('<x-toggle wire:model="enabled" label="Enable" />')
            ->assertSee('role="switch"', false)
            ->assertSee('type="checkbox"', false);
    }

    public function test_field_wires_label_help_and_error_to_the_control(): void
    {
        view()->share('errors', new ViewErrorBag);

        $html = $this->blade(
            '<x-field label="Name" for="name" help="Shown to clients." required><input id="name"></x-field>'
        );

        $html->assertSee('for="name"', false)
            ->assertSee('id="name-help"', false)
            ->assertSee('(required)')
            ->assertSee('Shown to clients.');
    }

    public function test_table_headers_expose_sort_state(): void
    {
        $html = $this->blade(<<<'BLADE'
            <x-table caption="Clients">
                <thead><tr>
                    <x-th sort="name" current="name" direction="desc">Name</x-th>
                    <x-th sort="sites" current="name" direction="desc">Sites</x-th>
                    <x-th>Status</x-th>
                </tr></thead>
                <tbody><tr><x-td>Acme</x-td><x-td align="right" nowrap>3</x-td><x-td>Active</x-td></tr></tbody>
            </x-table>
        BLADE);

        $html->assertSee('<caption', false)
            ->assertSee('aria-sort="descending"', false)
            ->assertSee('aria-sort="none"', false)
            ->assertSee('wire:click="sortBy(\'sites\')"', false)
            ->assertSee('overflow-x-auto', false);
    }

    public function test_breadcrumbs_mark_the_current_page(): void
    {
        $this->blade('<x-breadcrumbs :items="[[\'label\' => \'Clients\', \'href\' => \'/clients\'], [\'label\' => \'Acme\']]" />')
            ->assertSee('aria-label="Breadcrumb"', false)
            ->assertSee('href="/clients"', false)
            ->assertSee('aria-current="page"', false)
            ->assertSee('Acme');
    }

    public function test_icon_only_buttons_always_carry_an_accessible_name(): void
    {
        $this->blade('<x-icon-button icon="trash-can" label="Delete Acme" danger />')
            ->assertSee('aria-label="Delete Acme"', false)
            ->assertSee('cr-btn-icon-danger', false);

        $this->blade('<x-button icon="plus" label="Add" />')
            ->assertSee('aria-label="Add"', false);

        $this->blade('<x-button variant="primary" href="/reports/create" icon="plus">New report</x-button>')
            ->assertSee('wire:navigate', false)
            ->assertSee('cr-btn-primary', false);
    }

    public function test_alerts_use_the_right_live_region_role(): void
    {
        $this->blade('<x-alert variant="danger">Broken</x-alert>')->assertSee('role="alert"', false);
        $this->blade('<x-alert variant="ok">Fine</x-alert>')->assertSee('role="status"', false);
    }

    public function test_flash_renders_session_messages_and_a_toast_region(): void
    {
        session()->flash('status', 'Client saved.');

        $this->blade('<x-flash />')
            ->assertSee('Client saved.')
            ->assertSee('aria-live="polite"', false);
    }

    public function test_tabs_and_status_dots_are_labelled(): void
    {
        $this->blade('<x-tabs label="Settings" :items="[[\'label\' => \'General\', \'href\' => \'/settings\', \'active\' => true], [\'label\' => \'AI\', \'href\' => \'/settings/ai\']]" />')
            ->assertSee('aria-label="Settings"', false)
            ->assertSee('aria-current="page"', false);

        $this->blade('<x-status-dot variant="danger" label="Down" />')->assertSee('Down');
    }

    public function test_the_app_shell_sets_the_page_title_skip_link_and_landmarks(): void
    {
        $user = User::factory()->manager()->create();
        Client::factory()->create(['name' => 'Acme']);

        $response = $this->actingAs($user)->get('/clients');

        $response->assertOk()
            ->assertSee('<title>Clients · Client Reporter</title>', false)
            ->assertSee('Skip to content')
            ->assertSee('<nav aria-label="Main"', false)
            ->assertSee('<main id="main"', false)
            ->assertSee('aria-current="page"', false);
    }

    public function test_the_index_screens_render_as_tables_with_captions(): void
    {
        $admin = User::factory()->administrator()->create();
        Client::factory()->create(['name' => 'Acme']);

        foreach (['/clients' => 'Clients', '/sites' => 'Sites', '/reports' => 'Reports', '/users' => 'Users', '/templates' => 'Report templates'] as $path => $caption) {
            $response = $this->actingAs($admin)->get($path)->assertOk();

            if ($path === '/clients' || $path === '/users') {
                $response->assertSee('<caption class="sr-only">'.$caption.'</caption>', false);
            }
        }
    }

    public function test_branded_error_pages_render_on_the_guest_layout(): void
    {
        $this->get('/definitely-not-a-route')
            ->assertNotFound()
            ->assertSee('Page not found')
            ->assertSee('Client Reporter');
    }
}
