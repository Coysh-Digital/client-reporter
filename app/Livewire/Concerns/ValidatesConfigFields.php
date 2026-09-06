<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Integrations\Support\ConfigField;
use Illuminate\Support\Facades\Validator;

/**
 * Validates an integration's config fields as submitted through a connection
 * form (`values.*`) plus the connection name. Shared by the per-site and
 * workspace setup screens so both apply identical rules.
 */
trait ValidatesConfigFields
{
    /**
     * @param  array<int, ConfigField>  $fields
     * @param  array<string, mixed>  $values
     */
    protected function validateConfigFields(array $fields, array $values, string $name, bool $editing): void
    {
        $rules = [];
        $attributes = [];

        foreach ($fields as $field) {
            $rule = $field->validationRules();

            // On edit, a secret left blank means "keep existing".
            if ($field->secret && $editing) {
                $rule = array_map(fn ($r) => $r === 'required' ? 'nullable' : $r, $rule);
            }

            $rules["values.{$field->key}"] = $rule;
            $attributes["values.{$field->key}"] = $field->label;
        }

        Validator::make(
            ['values' => $values, 'name' => $name],
            array_merge($rules, ['name' => ['required', 'string', 'max:255']]),
            [],
            $attributes,
        )->validate();
    }
}
