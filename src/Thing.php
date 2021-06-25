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

    function val(string $field_name, string $lang = null): null | string | bool | int | array | File
    {
        $field = $this->resolveField($field_name);
        $codename = $field->identifier($lang);
        $default = $field->is_multiple ? [] : null;
        $values = $this->json->fields->{$codename} ?? $default;
        if (is_null($values)) return $default;
        if (!$field->is_multiple) $values = [$values];
        if (in_array($field->type, ['file', 'image'])) {
            $values = array_map(function ($v) {
                return new File($this->api, $v);
            }, $values);
        }
        return $field->is_multiple ? $values : $values[0];
    }

    function rel(string|array $path): ThingCollection
    {
        if (is_string($path)) {
            $path = explode('.', $path);
        }
        $field_name = array_shift($path); // remove first
        $field = $this->resolveField($field_name);
        $codename = $field->identifier();
        if (!isset($this->relations[$codename])) {
            $with = [];
            if (!empty($path)) {
                $with[] = implode('.', $path);
            }
            $this->loadRelation($field, $with);
        }
        $rel = $this->relations[$codename];

        if (!empty($path)) {
            $rel = $rel->map(function ($related) use ($path) {
                return $related->rel($path);
            })->flatten()->unique('id');
        }
        return $rel;
    }

    private function resolveField(string $field_name): Field
    {
        $res = $this->resource();
        $field = $res->field($field_name);
        if (!$field) throw new Exceptions\FieldNotFound("Cannot find field $field_name");
        return $field;
    }

    private function loadRelation(Field $field, array $with = [])
    {
        $result = $this->api->query($field->relatedResource()->name)
            ->relatedTo($field, $this->id)
            ->with($with)
            ->all();
        $this->setRelation($field, $result);
    }

    private function setRelation(Field $field, ThingCollection $things)
    {
        $this->relations[$field->identifier()] = $things;
    }

    function resource(): Resource
    {
        return $this->api->schema->resource($this->json->resource_id);
    }
}
