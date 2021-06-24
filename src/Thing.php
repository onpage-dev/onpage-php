<?php

namespace OnPage;

class Thing
{
    private object $json;
    public int $id;
    private Api $api;
    private array $relations = [];
    function __construct(Api $api, object $json)
    {
        $this->api = $api;
        $this->json = $json;
        $this->id = $json->id;
        foreach ($json->relations as $field_name => $related_things) {
            $this->setRelation($this->resource()->field($field_name), ThingCollection::fromResponse($api, $related_things));
        }
    }

    function val(string $field_name, string $lang = null): string | int | array | File
    {
        $field = $this->resolveField($field_name);
        $codename = $field->identifier($lang);
        $values = $this->json->fields?->{$codename};
        $default = $field->is_multiple ? [] : null;
        if (is_null($values)) return $default;
        if (!$field->is_multiple) $values = [$values];
        if (in_array($field->type, ['file', 'image'])) {
            $values = array_map(function($v) {
                return new File($this->api, $v);
            }, $values);
        }
        return $field->is_multiple ? $values : $values[0];
    }

    function rel(string $field_name): ThingCollection
    {
        $field = $this->resolveField($field_name);
        $codename = $field->identifier();
        if (!isset($this->relations[$codename])) {
            $this->loadRelation($field);
        }
        return $this->relations[$codename];
    }

    private function resolveField(string $field_name): Field
    {
        $res = $this->resource();
        $field = $res->field($field_name);
        if (!$field) throw new Exceptions\FieldNotFound("Cannot find field $field_name");
        return $field;
    }

    private function loadRelation(Field $field)
    {
        $result = $this->api->query($field->relatedResource()->name)->relatedTo($field, $this->id)->all();
        $this->setRelation($field, $result);
    }

    private function setRelation(Field $field, ThingCollection $things) {
        $this->relations[$field->identifier()] = $things;
    }

    function resource(): Resource
    {
        return $this->api->schema->resource($this->json->resource_id);
    }
}
