<?php

namespace OnPage;

use OnPage\Exceptions\FieldNotFound;

class Thing
{
    private object $json;
    public int $id;
    private Api $api;
    private array $relations = [];
    public function __construct(Api $api, object $json)
    {
        $this->api = $api;
        $this->json = $json;
        $this->id = $json->id;
        foreach ($json->relations as $field_name => $related_things) {
            $this->setRelation($this->resource()->field($field_name), ThingCollection::fromResponse($api, $related_things));
        }
    }

    function getOrder(): int
    {
        return $this->json->order;
    }

    public function val(string $field_path, string $lang = null) //: null | string | bool | int | array | File
    {
        $values = $this->values($field_path, $lang, $field);
        if ($field->is_multiple) return $values;
        return $values[0] ?? null;
    }
    public function values(string $field_path, string $lang = null, Field &$field = null): array
    {
        if ($field_path == '_id') return [$this->id];
        if ($field_path == '_resource_id') return [$this->json->resource_id];
        if ($field_path == '_created_at') return [$this->json->created_at];
        $path = $this->resource()->resolveFieldPath($field_path);
        $field = collect($path)->last();

        if (count($path) > 1) {
            $related = $this->rel($path->first()->name)->first();
            if (!$related) return [];
            return $related->values($path->skip(1)->pluck('name')->implode('.'), $lang, $field);
        }

        $codename = $field->identifier($lang);
        $values = $this->json->fields->{$codename} ?? null;
        if (is_null($values)) {
            return [];
        }
        if (!$field->is_multiple) {
            $values = [$values];
        }
        if (in_array($field->type, ['file', 'image'])) {
            $values = array_map(function ($v) {
                return new File($this->api, $v);
            }, array_filter($values, function ($x) {
                return !is_null($x);
            }));
        }
        return $values;
    }

    public function rel($path): ThingCollection
    {
        if (is_string($path)) {
            $path = explode('.', $path);
        }
        $field_name = array_shift($path); // remove first
        $field = $this->resolveField($field_name);
        $codename = $field->identifier();
        if (!isset($this->relations[$codename])) {
            if (!$this->api->allow_dynamic_relations) {
                throw new \Exception("The relation $codename was not loaded");
            }
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
        if (!$field) {
            throw FieldNotFound::from($field_name);
        }
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

    public function resource(): Resource
    {
        return $this->api->schema->resource($this->json->resource_id);
    }

    function editor(DataWriter $updater = null): ThingEditor
    {
        if (!$updater) $updater = $this->resource()->writer();
        return $updater->forThing($this->id);
    }
}
