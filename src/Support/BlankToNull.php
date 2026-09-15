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
}
