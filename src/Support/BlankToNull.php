<?php

namespace Modules\Custom\MakerBids\Support;

trait BlankToNull
{
    /**
     * @param  list<string>  $keys
     */
    protected function nullBlankFields(array $keys): void
    {
        $merge = [];
        foreach ($keys as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $merge[$key] = null;
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    /**
     * G7 select often posts {value,label} instead of a slug string.
     *
     * @param  list<string>  $keys
     */
    protected function coerceSlugFields(array $keys = ['type', 'kind', 'status', 'audience']): void
    {
        $merge = [];
        foreach ($keys as $key) {
            if (! $this->exists($key)) {
                continue;
            }
            $merge[$key] = $this->slugString($this->input($key));
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    protected function slugString(mixed $value): string
    {
        if (is_array($value)) {
            foreach (['value', 'slug', 'type', 'id'] as $k) {
                if (isset($value[$k]) && ! is_array($value[$k]) && (string) $value[$k] !== '') {
                    return trim((string) $value[$k]);
                }
            }
            $first = reset($value);

            return is_array($first) ? $this->slugString($first) : trim((string) $first);
        }
        if (is_object($value)) {
            return $this->slugString((array) $value);
        }
        if (is_bool($value)) {
            return $value ? '1' : '';
        }

        return trim((string) $value);
    }

    /**
     * @param  list<string>  $roots
     */
    protected function liftNestedFormFields(array $roots = ['form']): void
    {
        $merge = [];
        foreach ($roots as $root) {
            $nested = $this->input($root);
            if (! is_array($nested)) {
                continue;
            }
            foreach ($nested as $key => $value) {
                if (! is_string($key) || $key === '' || $this->filled($key)) {
                    continue;
                }
                $merge[$key] = $value;
            }
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }
}
