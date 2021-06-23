<?php

namespace OnPage;


class Resource
{
    private $id;
    private $label;
    private array $fields;
    private array $id_to_field;
    private array $name_to_field;
    private Api $api;
    function __construct(Api $api, object $json)
    {
        $this->api = $api;
        $this->id = $json->id;
        $this->name = $json->name;
        $this->label = $json->label;
        foreach ($json->fields as $field_json) {
            $field = new Field($this->api, $field_json);
            $this->fields[] = $field;
            $this->id_to_field[$field_json->id] = $field;
            $this->name_to_field[$field_json->name] = $field;
        }
    }

    function field(int|string $id): ?Field
    {
        if (is_numeric($id)) {
            return $this->id_to_field[$id] ?? null;
        } else {
            return $this->name_to_field[$id] ?? null;
        }
    }
}
