<?php

namespace OnPage;

use Illuminate\Support\Collection;

class ThingCollection extends Collection
{
    static function fromResponse(Schema $schema, array $json_things): self
    {
        $ret = new self();
        foreach ($json_things as $json) {
            $ret->push(new Thing($schema, $json));
        }
        return $ret;
    }
}
