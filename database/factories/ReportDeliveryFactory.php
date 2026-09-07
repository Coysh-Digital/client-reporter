<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DeliveryTrigger;
use App\Models\Report;
use App\Models\ReportDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReportDelivery>
 */
class ReportDeliveryFactory extends Factory
{
    protected $model = ReportDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'report_id' => Report::factory(),
            'recipient' => $this->faker->safeEmail(),
            'trigger' => DeliveryTrigger::Manual,
            'included_pdf' => true,
            'succeeded' => true,
            'error' => null,
            'created_by' => null,
        ];
    }

    public function auto(): static
    {
        return $this->state(fn (array $attributes): array => ['trigger' => DeliveryTrigger::Auto]);
    }

    public function failed(string $error = 'Delivery failed.'): static
    {
        return $this->state(fn (array $attributes): array => ['succeeded' => false, 'error' => $error]);
    }
}
