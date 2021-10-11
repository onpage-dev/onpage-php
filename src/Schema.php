<?php

namespace OnPage;

class Schema
{
    public $id;
    public $label;
    private Api $api;
    private array $id_to_resource;
    private array $id_to_field;
    private array $name_to_resource;
    private array $resources;
    public array $langs;

    public function __construct(Api $api, object $json)
    {
        $this->api = $api;
        $this->id = $json->id;
        $this->label = $json->label;
        $this->langs = $json->langs;
        foreach ($json->resources as $res_json) {
            $res = new Resource($this->api, $res_json);
            $this->resources[] = $res;
            $this->id_to_resource[$res_json->id] = $res;
            $this->name_to_resource[$res_json->name] = $res;
            foreach ($res->fields() as $field) {
                $this->id_to_field[$field->id] = $field;
            }
        }
    }

    public function resource($id): Resource
    {
        if (is_numeric($id)) {
            return $this->id_to_resource[$id] ?? null;
        } else {
            return $this->name_to_resource[$id] ?? null;
        }
    }
    public function resources(): array
    {
        return array_values($this->id_to_resource);
    }
    function fields(): array
    {
        return array_values($this->id_to_field);
    }
}
