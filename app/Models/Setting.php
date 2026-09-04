<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Settings;
use Illuminate\Database\Eloquent\Model;

/**
 * A single application-wide key/value setting. Values are JSON-encoded, so a
 * setting may hold a scalar, list or associative array. Access settings through
 * the {@see Settings} repository rather than this model directly so
 * reads are cached and writes invalidate the cache.
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
