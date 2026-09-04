<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $issuedAt = fake()->dateTimeBetween('-2 months', 'now');

        return [
            'client_id' => Client::factory(),
            'number' => 'INV-'.fake()->unique()->numerify('######'),
            'description' => fake()->optional()->sentence(4),
            'amount' => fake()->randomFloat(2, 100, 5000),
            'currency' => 'GBP',
            'status' => InvoiceStatus::Sent,
            'issued_at' => $issuedAt,
            'due_at' => (clone $issuedAt)->modify('+14 days'),
            'paid_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => $attributes['due_at'] ?? now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => InvoiceStatus::Draft]);
    }
}
