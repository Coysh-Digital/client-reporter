<?php

declare(strict_types=1);

namespace Tests\Feature\Mcp;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MakeMcpTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_mints_a_read_token_for_a_staff_user(): void
    {
        $user = User::factory()->manager()->create(['email' => 'staff@example.com']);

        $this->artisan('client-reporter:mcp-token', ['email' => 'staff@example.com'])
            ->assertSuccessful();

        $this->assertCount(1, $user->refresh()->tokens);
        $this->assertSame(['mcp:read'], $user->tokens->first()->abilities);
        $this->assertNotNull($user->tokens->first()->expires_at);
        $this->assertTrue($user->tokens->first()->expires_at->isBetween(now()->addDays(29), now()->addDays(31)));
    }

    public function test_the_expiry_can_be_chosen_and_tokens_revoked(): void
    {
        $user = User::factory()->manager()->create(['email' => 'staff@example.com']);

        $this->artisan('client-reporter:mcp-token', ['email' => 'staff@example.com', '--expires-days' => 7])->assertSuccessful();
        $this->assertTrue($user->refresh()->tokens->first()->expires_at->isBetween(now()->addDays(6), now()->addDays(8)));

        $this->artisan('client-reporter:mcp-token', ['email' => 'staff@example.com', '--revoke' => true])->assertSuccessful();
        $this->assertCount(0, $user->refresh()->tokens);
    }

    public function test_it_refuses_a_non_staff_user(): void
    {
        User::factory()->client()->create(['email' => 'client@example.com']);

        $this->artisan('client-reporter:mcp-token', ['email' => 'client@example.com'])
            ->assertFailed();
    }

    public function test_it_refuses_an_unknown_email(): void
    {
        $this->artisan('client-reporter:mcp-token', ['email' => 'nobody@example.com'])
            ->assertFailed();
    }
}
