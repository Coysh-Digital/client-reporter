<?php

declare(strict_types=1);

namespace App\Rules;

use App\Support\Http\OutboundUrl;
use App\Support\Http\UnsafeUrlException;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Passes only for http(s) URLs whose host resolves to a public address (see
 * OutboundUrl). Pair it with `url:http,https`; an empty value is left to the
 * `required`/`nullable` rules.
 */
final class PublicUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;
        }

        try {
            OutboundUrl::check($value);
        } catch (UnsafeUrlException $e) {
            $fail('The :attribute must be a public web address (not a local or private network address). '.$e->getMessage());
        }
    }
}
