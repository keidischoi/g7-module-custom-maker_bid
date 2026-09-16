<?php

namespace Modules\Custom\MakerBid\Support;

trait BooleanishFields
{
    /**
     * @param  list<string>  $keys
     */
    protected function coerceBooleanFields(array $keys): void
    {
        $merge = [];
        foreach ($keys as $key) {
            if (! $this->exists($key)) {
                continue;
            }
            $merge[$key] = $this->isTruthy($this->input($key));
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    protected function isTruthy(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_int($value) || is_float($value)) {
            return (int) $value === 1;
        }
        if (! is_string($value)) {
            return false;
        }
        $value = strtolower(trim($value));

        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }
}
