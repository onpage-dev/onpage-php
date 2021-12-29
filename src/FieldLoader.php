<?php

namespace OnPage;

class FieldLoader {
    public ?string $relation;
    public ?array $fields = ['+'];
    public ?array $relations = [];

    function __construct(string $relation = null)
    {
        $this->relation = $relation;
    }

    function setRelation(string $rel_name) : FieldLoader {
        if (!isset($this->relations[$rel_name])) {
            $this->relations[$rel_name] = new FieldLoader($rel_name);
        }
        return $this->relations[$rel_name];
    }

    function loadFields(array $fields) {
        $this->fields = $fields;
    }

    function encode() {
        $ret = $this->fields;
        foreach ($this->relations as $rel) {
            $ret[] = $rel->encode();
        }
        if ($this->relation) {
            $ret = [
                $this->relation,
                $ret,
            ];
        }
        return $ret;
    }
}