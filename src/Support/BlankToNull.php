<?php

namespace Modules\Custom\MakerBid\Support;

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
     * G7 sometimes posts the dataKey object nested as `form` / `company`
     * instead of flattening `name` fields onto the root.
     *
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
