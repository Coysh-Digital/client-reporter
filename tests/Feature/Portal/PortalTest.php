<?php

declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Livewire\Auth\Login;
use App\Models\Client;
use App\Models\Report;
use App\Models\Site;
use App\Models\User;
use App\Reporting\ReportGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    private function generatedReportForClient(Client $client, string $title = 'Monthly website report'): Report
    {
        $site = Site::factory()->for($client)->create();
        $report = Report::factory()->for($site)->create(['title' => $title]);
        $report->blocks()->create(['type' => 'cover', 'position' => 0]);
        app(ReportGenerator::class)->generate($report);

        return $report->refresh();
    }

    public function test_a_client_sees_only_their_own_reports(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $mine = $this->generatedReportForClient($client, 'Alpha August Report');
        $theirs = $this->generatedReportForClient($other, 'Beta August Report');

        $user = User::factory()->client()->create(['client_id' => $client->id]);

        $this->actingAs($user)->get(route('portal.dashboard'))
            ->assertOk()
            ->assertSee($mine->title)
            ->assertDontSee($theirs->title);
    }

    public function test_a_client_cannot_view_another_clients_report(): void
    {
        $client = Client::factory()->create();
        $other = Client::factory()->create();
        $theirs = $this->generatedReportForClient($other);

        $user = User::factory()->client()->create(['client_id' => $client->id]);

        $this->actingAs($user)->get(route('portal.report', $theirs))->assertForbidden();
    }

    public function test_a_client_can_view_their_own_report(): void
    {
        $client = Client::factory()->create();
        $mine = $this->generatedReportForClient($client);
        $user = User::factory()->client()->create(['client_id' => $client->id]);

        $this->actingAs($user)->get(route('portal.report', $mine))->assertOk()->assertSee($client->name);
    }

    public function test_staff_cannot_access_the_portal(): void
    {
        $staff = User::factory()->manager()->create();

        $this->actingAs($staff)->get(route('portal.dashboard'))->assertForbidden();
    }

    public function test_a_client_cannot_access_the_admin(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->client()->create(['client_id' => $client->id]);

        $this->actingAs($user)->get('/dashboard')->assertForbidden();
    }

    public function test_a_client_is_redirected_to_the_portal_on_login(): void
    {
        $client = Client::factory()->create();
        $user = User::factory()->client()->create(['client_id' => $client->id]);

        Livewire::test(Login::class)
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('portal.dashboard', absolute: false));
    }
}
