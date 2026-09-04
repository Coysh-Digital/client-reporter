<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A reusable set of ordered block definitions an agency can apply to any site.
 *
 * @property array<int, array<string, mixed>> $blocks
 */
class ReportTemplate extends Model
{
    protected $fillable = [
        'name',
        'description',
        'blocks',
    ];

    protected function casts(): array
    {
        return [
            'blocks' => 'array',
        ];
    }
}
