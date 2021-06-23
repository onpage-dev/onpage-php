<?php

namespace OnPage;

use Countable;
use Illuminate\Support\Collection;
use Iterator;

class ThingCollection extends Collection
{

    function __construct()
    {
        $this->collection = collect();
    }

    static function fromResponse(Api $api, array $json_things): self
    {
        $ret = new self();
        foreach ($json_things as $json) {
            $ret->push(new Thing($api, $json));
        }
        return $ret;
    }

    // function first(): Thing
    // {
    //     return $this->collection->first();
    // }


    // public function __call($name, $arguments)
    // {
    //     if (method_exists($this, $name)) {
    //         return $this->$name(...$arguments);
    //     } else {
    //         return $this->collection->$name(...$arguments);
    //     }
    // }

    // // Countable methods
    // public function count(): int
    // {
    //     return $this->collection->count();
    // }


    // // Iterator methods
    // public function current(): mixed
    // {
    //     return $this->collection->current();
    // }
    // public function key(): mixed
    // {
    //     return $this->collection->key();
    // }
    // public function next(): void
    // {
    //     $this->collection->next();
    // }
    // public function rewind(): void
    // {
    //     $this->collection->rewind();
    // }
    // public function valid(): bool
    // {
    //     return $this->collection->valid();
    // }
}
