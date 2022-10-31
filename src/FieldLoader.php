<?php

namespace OnPage;

class FieldLoader
{
    public ?Field $relation = null;
    public ?array $fields = ['+'];
    public ?array $relations = [];
    public ?array $filters = [];
    public ?string $as = null;

    function __construct(Field $relation = null, string $as = null)
    {
        $this->relation = $relation;
        $this->as = $as;
    }

    function setRelation(Field $field, string $rel_name = null): FieldLoader
    {
        if (!$rel_name) $rel_name = $field->name;
        if (!isset($this->relations[$rel_name])) {
            $this->relations[$rel_name] = new FieldLoader($field, $rel_name);
        }
        return $this->relations[$rel_name];
    }

    /** @param string|array $rel_name */
    function getRelation($rel_name): FieldLoader
    {
        if (!is_array($rel_name)) $rel_name = explode('.', $rel_name);
        $current = array_shift($rel_name);
        $loader = $this->relations[$current];
        if (empty($rel_name)) return $loader;
        return $loader->getRelation($rel_name);
    }

    function loadFields(array $fields, bool $append = false)
    {
        if ($append) {
            $this->fields = array_merge($this->fields, $fields);
        } else {
            $this->fields = $fields;
        }
        return $this;
    }

    function encode()
    {
        $ret = $this->fields;
        foreach ($this->relations as $rel) {
            $ret[] = $rel->encode();
        }
        if ($this->relation) {
            $ret = [
                'field' => $this->relation->name,
                'fields' => $ret,
                'as' => $this->as,
                'filters' => $this->filters,
            ];
        }
        return $ret;
    }
}
