<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class MakeMcpToken extends Command
{
    protected $signature = 'client-reporter:mcp-token
        {email : The email of the staff user the token belongs to}
        {--name=MCP access : A label for the token}
        {--expires-days=30 : Days until the token expires (1–365)}
        {--revoke : Revoke every MCP token this user holds instead of creating one}';

    protected $description = 'Create (or revoke) a Sanctum token for read-only MCP (HTTP) access, tied to a staff user';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $this->error("No user found with email {$email}.");

            return self::FAILURE;
        }

        if ($this->option('revoke')) {
            $count = $user->tokens()->delete();
            $this->info("Revoked {$count} MCP token(s) for {$user->email}.");

            return self::SUCCESS;
        }

        if (! $user->isStaff()) {
            $this->error("{$email} is not a staff account. MCP tokens are for agency staff (Administrator, Manager or Viewer).");

            return self::FAILURE;
        }

        if (! $user->is_active) {
            $this->error("{$email} is deactivated. Re-activate the account before issuing a token.");

            return self::FAILURE;
        }

        $days = max(1, min(365, (int) $this->option('expires-days')));
        $expiresAt = now()->addDays($days);

        $token = $user->createToken((string) $this->option('name'), ['mcp:read'], $expiresAt);

        $this->newLine();
        $this->info("MCP token created for {$user->name} <{$user->email}>. It expires on {$expiresAt->toDateString()}.");
        $this->newLine();
        $this->line('  '.$token->plainTextToken);
        $this->newLine();
        $this->warn('Copy it now — it is shown only once. Send it as a Bearer token to POST '.rtrim((string) config('app.url'), '/').'/mcp');

        return self::SUCCESS;
    }
}
