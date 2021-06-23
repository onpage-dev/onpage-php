<?php

namespace OnPage;


class Schema
{
    public $id;
    public $label;
    private Api $api;
    private array $id_to_resource;
    private array $name_to_resource;
    private array $resources;
    public array $langs;

    function __construct(Api $api, object $json)
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
        }
    }

    function resource(int|string $id): ?Resource
    {
        if (is_numeric($id)) {
            return $this->id_to_resource[$id] ?? null;
        } else {
            return $this->name_to_resource[$id] ?? null;
        }
    }
}
