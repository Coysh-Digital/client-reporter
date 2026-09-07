<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\ConnectionStatus;
use App\Integrations\CollectorRunner;
use App\Livewire\Notifications\Bell;
use App\Models\SiteIntegration;
use App\Models\User;
use App\Notifications\IntegrationFailed;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class IntegrationNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function mailchimp(ConnectionStatus $status = ConnectionStatus::Connected): SiteIntegration
    {
        return SiteIntegration::factory()->create([
            'integration_key' => 'mailchimp',
            'status' => $status,
            'credentials' => ['api_key' => 'key-us1'],
            'settings' => ['list_id' => 'abc'],
        ]);
    }

    public function test_an_expired_authentication_notifies_managers_and_admins_only(): void
    {
        $admin = User::factory()->administrator()->create();
        $manager = User::factory()->manager()->create();
        $viewer = User::factory()->viewer()->create();

        Http::fake(['us1.api.mailchimp.com/*' => Http::response('', 401)]);

        app(CollectorRunner::class)->collectAll($this->mailchimp(), DateRange::thisMonth());

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $manager->notifications()->count());
        $this->assertSame(0, $viewer->notifications()->count());

        $data = $manager->notifications()->first()->data;
        $this->assertStringContainsString('Mailchimp', $data['title']);
        $this->assertStringContainsString('authentication', $data['body']);
    }

    public function test_a_transient_failure_does_not_notify(): void
    {
        $manager = User::factory()->manager()->create();
        Http::fake(['us1.api.mailchimp.com/*' => Http::response('down', 500)]);

        $connection = $this->mailchimp();
        app(CollectorRunner::class)->collectAll($connection, DateRange::thisMonth());

        $this->assertSame(ConnectionStatus::NeedsAttention, $connection->fresh()->status);
        $this->assertSame(0, $manager->notifications()->count());
    }

    public function test_auto_disable_notifies_once_and_not_again(): void
    {
        config(['client-reporter.collection.failure_threshold' => 2]);
        $manager = User::factory()->manager()->create();
        Http::fake(['us1.api.mailchimp.com/*' => Http::response('down', 500)]);

        $connection = $this->mailchimp();
        $runner = app(CollectorRunner::class);

        $runner->collectAll($connection, DateRange::thisMonth()); // failure 1 → needs attention
        $this->assertSame(0, $manager->notifications()->count());

        $runner->collectAll($connection, DateRange::thisMonth()); // failure 2 → disabled → notify
        $this->assertSame(1, $manager->notifications()->count());

        // Regenerating a report could re-collect a disabled connection — it must
        // not raise the alert a second time.
        $runner->collectAll($connection, DateRange::thisMonth());
        $this->assertSame(1, $manager->notifications()->count());
    }

    public function test_the_bell_shows_unread_and_can_mark_all_read(): void
    {
        $manager = User::factory()->manager()->create();
        $manager->notify(new IntegrationFailed('Mailchimp needs attention', 'Reconnect it.', route('dashboard')));

        Livewire::actingAs($manager)->test(Bell::class)
            ->assertSee('Mailchimp needs attention')
            ->call('markAllRead');

        $this->assertSame(0, $manager->fresh()->unreadNotifications()->count());
    }

    public function test_opening_a_notification_marks_it_read_and_redirects(): void
    {
        $manager = User::factory()->manager()->create();
        $manager->notify(new IntegrationFailed('Mailchimp needs attention', 'Reconnect it.', route('dashboard')));
        $id = $manager->notifications()->first()->id;

        Livewire::actingAs($manager)->test(Bell::class)
            ->call('open', $id)
            ->assertRedirect(route('dashboard'));

        $this->assertNotNull($manager->notifications()->first()->read_at);
    }
}
