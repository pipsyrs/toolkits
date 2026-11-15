<?php

namespace App\Livewire\Concerns;

use Illuminate\Support\Str;
use ReflectionNamedType;
use ReflectionProperty;

// Tampered update payloads (wrong type) get 422 instead of a 500 TypeError.
// ValidationException is swallowed by Livewire here and the set would still run.
trait GuardsPropertyTypes
{
    public function updatingGuardsPropertyTypes(string $name, mixed $value): void
    {
        $root = Str::before($name, '.');

        if (! property_exists($this, $root)) {
            return;
        }

        $type = (new ReflectionProperty($this, $root))->getType();

        if (! $type instanceof ReflectionNamedType) {
            return;
        }

        $valid = match (true) {
            str_contains($name, '.') => $type->getName() === 'array' && ($value === null || is_scalar($value)),
            $value === null => $type->allowsNull(),
            $type->getName() === 'array' => is_array($value),
            in_array($type->getName(), ['int', 'float'], true) => is_numeric($value),
            in_array($type->getName(), ['string', 'bool'], true) => is_scalar($value),
            default => true,
        };

        abort_unless($valid, 422, 'Format data tidak valid.');
    }
}
