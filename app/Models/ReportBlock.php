<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed>|null $config
 * @property bool $is_hidden
 */
class ReportBlock extends Model
{
    protected $fillable = [
        'report_id',
        'type',
        'position',
        'heading',
        'config',
        'commentary',
        'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'is_hidden' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Report, $this>
     */
    public function report(): BelongsTo
    {
        return $this->belongsTo(Report::class);
    }

    public function configValue(string $key, mixed $default = null): mixed
    {
        return ($this->config ?? [])[$key] ?? $default;
    }
}
