<?php

namespace OnPage;


class Field
{
    public int $id;
    public string $label;
    public bool $is_translatable;
    public bool $is_multiple;
    public ?int $rel_res_id;
    public ?int $rel_field_id;
    private string $type;
    private Api $api;
    function __construct(Api $api, object $json)
    {
        $this->api = $api;
        $this->id = $json->id;
        $this->is_multiple = $json->is_multiple;
        $this->is_translatable = $json->is_translatable;
        $this->name = $json->name;
        $this->label = $json->label;
        $this->type = $json->type;
        $this->rel_res_id = $json->rel_res_id;
        $this->rel_field_id = $json->rel_field_id;
    }

    function identifier(string $lang = null): string
    {
        $identifier = $this->name;
        if ($this->is_translatable) {
            if (!$lang) {
                $lang = $this->api->schema->langs[0];
            }
            $identifier .= "_$lang";
        }
        return $identifier;
    }
    function resource(): Resource
    {
        return $this->api->schema->resource($this->json->resource_id);
    }

    function relatedResource(): Resource
    {
        if (!$this->rel_res_id) {
            throw new \Exception("Field $this->name has no related resource");
        }
        return $this->api->schema->resource($this->rel_res_id);
    }
    function relatedField(): Field
    {
        $rel_res = $this->relatedResource();
        return $rel_res->field($this->rel_field_id);
    }
}
