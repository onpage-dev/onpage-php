<?php

namespace OnPage;

use Countable;
use Illuminate\Support\Collection;
use Iterator;

class ThingCollection extends Collection
{
    static function fromResponse(Api $api, array $json_things): self
    {
        $ret = new self();
        foreach ($json_things as $json) {
            $ret->push(new Thing($api, $json));
        }
        return $ret;
    }
}