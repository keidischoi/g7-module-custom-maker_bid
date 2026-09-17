<?php

namespace Modules\Custom\MakerBids\Support;

trait BlankToNull
{
    /**
     * @param  list<string>  $keys
     */

    /**
     * Remove empty-string keys so partial updates skip them (isset/array_key_exists false).
     *
     * @param  list<string>  $keys
     */
    protected function dropBlankKeys(array $keys): void
    {
        foreach ($keys as $key) {
            if ($this->exists($key) && $this->input($key) === '') {
                $this->offsetUnset($key);
            }
        }
    }

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
     * @param  list<string>  $keys
     */
    protected function coerceSlugFields(array $keys = ['type', 'kind', 'status', 'audience', 'title']): void
    {
        $merge = [];
        foreach ($keys as $key) {
            if (! $this->exists($key)) {
                continue;
            }
            $merge[$key] = $this->slugString($this->input($key), $key);
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    protected function slugString(mixed $value, string $prefer = 'value'): string
    {
        if (is_array($value)) {
            foreach ([$prefer, 'title', 'value', 'slug', 'type', 'name', 'id'] as $k) {
                if (isset($value[$k]) && ! is_array($value[$k]) && (string) $value[$k] !== '') {
                    return trim((string) $value[$k]);
                }
            }
            if (array_is_list($value) && isset($value[0]) && is_string($value[0])) {
                return trim((string) $value[0]);
            }
            $first = reset($value);

            return is_array($first) ? $this->slugString($first, $prefer) : trim((string) $first);
        }
        if (is_object($value)) {
            return $this->slugString((array) $value, $prefer);
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
/**
     * Map Korean / legacy status labels to listing slugs and fold ext_* flags
     * into provided_extensions before validation (validated() strips unknown keys).
     */
    protected function normalizeJobStatusAndExtensions(): void
    {
        if ($this->exists('status')) {
            $this->merge(['status' => JobRules::normalizeListingStatus($this->input('status'))]);
        }
        // Coerce any ext_* checkbox (including settings-driven ones like ext_pdf).
        $boolMerge = [];
        foreach ($this->all() as $key => $value) {
            if (is_string($key) && str_starts_with($key, 'ext_')) {
                $boolMerge[$key] = $this->isTruthyExt($value);
            }
        }
        if ($boolMerge !== []) {
            $this->merge($boolMerge);
        }
        // Allow settings-driven ext_* beyond built-in defaults (e.g. PDF already default).
        $allowed = UploadRules::PROVIDED_EXTENSIONS;
        foreach ($this->all() as $key => $_) {
            if (is_string($key) && str_starts_with($key, 'ext_')) {
                $tok = strtoupper(substr($key, 4));
                if ($tok !== '' && ! in_array($tok, $allowed, true)) {
                    $allowed[] = $tok;
                }
            }
        }
        if (is_array($this->input('provided_extensions'))) {
            foreach (UploadRules::parseExtensionList($this->input('provided_extensions')) as $tok) {
                if (! in_array($tok, $allowed, true)) {
                    $allowed[] = $tok;
                }
            }
        }
        $collected = JobRules::collectProvidedExtensions($this->all(), $allowed);
        if ($collected !== []) {
            $this->merge(['provided_extensions' => $collected]);
        }
    }

    private function isTruthyExt(mixed $value): bool
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
