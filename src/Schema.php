<?php

namespace OnPage;

use Illuminate\Support\Collection;

class Schema
{
    public $id;
    public $label;
    private AbstractApi $api;
    private array $id_to_resource;
    private array $id_to_field;
    private array $name_to_resource;
    private array $resources;
    public array $langs;
    public string $lang;

    public function __construct(AbstractApi $api, object $json)
    {
        $this->api = $api;
        $this->id = $json->id;
        $this->label = $json->label;
        $this->langs = $json->langs;
        $this->lang = $this->langs[0];
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

    public function resource($id): ?Resource
    {
        if (is_numeric($id)) {
            return $this->id_to_resource[$id] ?? null;
        } else {
            return $this->name_to_resource[$id] ?? null;
        }
    }

    /** @return Collection<Resource> */
    public function resources(): Collection
    {
        return collect($this->id_to_resource);
    }

    /** @return Collection<Field> */
    function fields(): Collection
    {
        return collect($this->id_to_field);
    }

    function query(string $resource): QueryBuilder
    {
        return $this->resource($resource)->query();
    }
}
