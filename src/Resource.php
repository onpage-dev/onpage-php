<?php

namespace OnPage;

use Illuminate\Support\Collection;
use OnPage\Exceptions\FieldNotFound;

class Resource
{
    public int $id;
    public string $label;
    public string $name;
    public array $labels;
    private array $fields;
    private array $id_to_field;
    private array $name_to_field;
    private AbstractApi $api;
    public function __construct(AbstractApi $api, object $json)
    {
        $this->api = $api;
        $this->id = $json->id;
        $this->name = $json->name;
        $this->label = $json->label;
        $this->labels = (array) $json->labels;
        foreach ($json->fields as $field_json) {
            $field = new Field($this->api, $field_json);
            $this->fields[] = $field;
            $this->id_to_field[$field_json->id] = $field;
            $this->name_to_field[$field_json->name] = $field;
        }
    }

    function getLabel(?string $lang = null) : string
    {
        if (isset($this->labels[$lang])) return $this->labels[$lang];
        $lang = $this->api->schema->lang;
        if (isset($this->labels[$lang])) return $this->labels[$lang];
        return $this->name;
    }
    
    public function field($id): ?Field
    {
        $field = null;
        if (is_numeric($id)) {
            $field = $this->id_to_field[$id] ?? null;
        } else {
            $field = $this->name_to_field[$id] ?? null;
        }
        if ($field) $field->markAsUsed();
        return $field;
    }

    /**
     * @return Collection<Field>
     */
    public function fields(): Collection
    {
        return collect($this->fields);
    }

    function writer(): DataWriter
    {
        return new DataWriter($this->api, $this);
    }

    function query(): QueryBuilder
    {
        return new QueryBuilder($this->api, $this);
    }

    /**
     * @param array|string $field_path
     * @return Collection<Field>
     */
    function resolveFieldPath($field_path): Collection
    {
        if (is_string($field_path)) {
            $field_path = explode('.', $field_path);
        }
        
        $current_res = $this;

        /** @var Field[] */
        $ret = [];
        foreach ($field_path as $field_i => $field_name) {
            $field = $current_res->field($field_name);
            if (!$field) throw FieldNotFound::from($field_name);
            $ret[] = $field;
            if ($field_i + 1 < count($field_path)) {
                $current_res = $field->relatedResource();
            }
        }
        return collect($ret);
    }
}
