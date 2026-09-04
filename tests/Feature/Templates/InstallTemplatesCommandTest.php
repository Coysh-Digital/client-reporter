<?php

declare(strict_types=1);

namespace Tests\Feature\Templates;

use App\Models\ReportTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstallTemplatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_installs_the_templates_on_a_live_site(): void
    {
        $this->assertSame(0, ReportTemplate::query()->count());

        $this->artisan('client-reporter:install-templates')
            ->expectsOutputToContain('Installed')
            ->assertSuccessful();

        $this->assertGreaterThan(0, ReportTemplate::query()->count());
        $this->assertDatabaseHas('report_templates', ['name' => 'Website Care Report']);
    }

    public function test_running_again_adds_nothing_and_keeps_edits(): void
    {
        $this->artisan('client-reporter:install-templates')->assertSuccessful();
        $count = ReportTemplate::query()->count();

        ReportTemplate::query()->where('name', 'Ecommerce Report')->update(['description' => 'Edited.']);

        $this->artisan('client-reporter:install-templates')
            ->expectsOutputToContain('already installed')
            ->assertSuccessful();

        $this->assertSame($count, ReportTemplate::query()->count());
        $this->assertSame('Edited.', ReportTemplate::query()->where('name', 'Ecommerce Report')->value('description'));
    }
}
