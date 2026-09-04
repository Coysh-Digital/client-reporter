<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
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

        $this->call(ReportTemplateSeeder::class);
    }
}
