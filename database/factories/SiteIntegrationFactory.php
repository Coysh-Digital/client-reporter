<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ConnectionStatus;
use App\Models\Site;
use App\Models\SiteIntegration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteIntegration>
 */
class SiteIntegrationFactory extends Factory
{
    protected $model = SiteIntegration::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'integration_key' => 'uptimerobot',
            'name' => 'UptimeRobot',
            'status' => ConnectionStatus::Connected,
            'credentials' => ['api_key' => 'test-key'],
            'settings' => [],
        ];
    }

    public function uptimeRobot(): static
    {
        return $this->state(fn () => ['integration_key' => 'uptimerobot', 'name' => 'UptimeRobot']);
    }
}
