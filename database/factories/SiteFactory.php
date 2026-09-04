<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Client;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    protected $model = Site::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'client_id' => Client::factory(),
            'name' => fake()->domainWord().' website',
            'url' => 'https://'.fake()->domainName(),
            'cms_type' => fake()->randomElement([null, 'wordpress', 'craft', 'other']),
            'environment' => 'production',
            'timezone' => 'UTC',
            'is_active' => true,
            'settings' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
