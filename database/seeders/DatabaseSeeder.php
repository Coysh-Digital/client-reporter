<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\ReportTemplate;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed a first administrator for local development. Production installs
     * create the administrator through the browser installation wizard.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Agency Admin',
                'password' => Hash::make('password'),
                'role' => UserRole::Administrator,
                'is_active' => true,
            ]
        );

        ReportTemplate::query()->firstOrCreate(
            ['name' => 'Standard Website Care Report'],
            [
                'description' => 'Cover, introduction, website overview, uptime and a closing message.',
                'blocks' => [
                    ['type' => 'cover', 'heading' => 'Cover'],
                    ['type' => 'text', 'heading' => 'Introduction'],
                    ['type' => 'website-overview', 'heading' => 'Website overview'],
                    ['type' => 'uptime.summary', 'heading' => 'Uptime & availability'],
                    ['type' => 'uptime.incidents', 'heading' => 'Incidents'],
                    ['type' => 'closing', 'heading' => 'Thank you'],
                ],
            ]
        );
    }
}
