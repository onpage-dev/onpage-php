<?php

namespace OnPage;

use Illuminate\Support\Collection;

class FieldFolder
{
    public int $id;
    public bool $is_default;
    public ?string $name;
    public float $order;
    public string $label;
    public array $labels;
    public int $resource_id;
    public int $schema_id;
    public string $created_at;
    public string $updated_at;
    public array $form_fields;
    public array $arrow_fields;

    function __construct(private AbstractApi $api, object $json)
    {
        $this->id = $json->id;
        $this->is_default = $json->is_default;
        $this->name = $json->name;
        $this->order = $json->order;
        $this->label = $json->label;
        $this->labels = (array) $json->labels;
        $this->resource_id = $json->resource_id;
        $this->schema_id = $json->schema_id;
        $this->created_at = $json->created_at;
        $this->updated_at = $json->updated_at;
        $this->form_fields = $json->form_fields;
        $this->arrow_fields = $json->arrow_fields;
    }


    function getLabel(?string $lang = null): string
    {
        if (isset($this->labels[$lang])) return $this->labels[$lang];
        $lang = $this->api->schema->lang;
        if (isset($this->labels[$lang])) return $this->labels[$lang];
        return $this->name;
    }

    function resource(): Resource
    {
        return $this->api->schema->resource($this->resource_id);
    }

    /** @return Collection<Field> */
    function getFormFields(): Collection
    {
        return collect($this->form_fields)->map(fn (int $id) => $this->resource()->field($id))->filter()->values();
    }
}
