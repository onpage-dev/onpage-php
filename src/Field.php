<?php

namespace OnPage;


class Field
{
    public int $id;
    public string $label;
    public string $name;
    public string $type;
    public array $labels;
    public bool $is_translatable;
    public bool $is_multiple;
    private bool $has_been_used = false;
    public ?int $rel_res_id;
    public ?int $rel_field_id;
    public int $resource_id;
    private AbstractApi $api;
    function __construct(AbstractApi $api, object $json)
    {
        $this->api = $api;
        $this->id = $json->id;
        $this->is_multiple = $json->is_multiple;
        $this->is_translatable = $json->is_translatable;
        $this->name = $json->name;
        $this->label = $json->label;
        $this->labels = (array) $json->labels;
        $this->type = $json->type;
        $this->resource_id = $json->resource_id;
        $this->rel_res_id = $json->rel_res_id;
        $this->rel_field_id = $json->rel_field_id;
    }

    function markAsUsed()
    {
        $this->has_been_used = true;
    }
    function hasBeenUsed()
    {
        return $this->has_been_used;
    }

    function identifier(string $lang = null): string
    {
        $identifier = $this->name;
        if ($this->is_translatable) {
            if (!$lang) {
                $lang = $this->api->schema->lang;
            }
            $identifier .= "_$lang";
        }
        return $identifier;
    }
    function resource(): Resource
    {
        return $this->api->schema->resource($this->resource_id);
    }

    function isMedia(): bool
    {
        return in_array($this->type, ['file', 'image']);
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
