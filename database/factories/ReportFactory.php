<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Report;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Report>
 */
class ReportFactory extends Factory
{
    protected $model = Report::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'title' => 'Monthly website report',
            'range_start' => '2026-08-01',
            'range_end' => '2026-08-31',
            'compare_previous' => true,
            'status' => 'draft',
        ];
    }
}
