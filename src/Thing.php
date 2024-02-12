<?php

namespace OnPage;

use Illuminate\Support\Collection;
use JsonSerializable;
use OnPage\Exceptions\FieldNotFound;

class Thing implements JsonSerializable
{
    private object $json;
    public int $id;
    public string $created_at;
    public string $updated_at;
    private AbstractApi $api;
    private array $relations = [];
    public function __construct(AbstractApi $api, object $json)
    {
        $this->api = $api;
        $this->json = $json;
        $this->id = $json->id;
        $this->created_at = $json->created_at;
        $this->updated_at = $json->updated_at;
        foreach ($json->relations as $field_name => $related_things) {
            $this->setRelation($this->resource()->field($field_name), ThingCollection::fromResponse($api, $related_things));
        }
    }

    function getOrder(): float
    {
        return $this->id / 100_000_000 + $this->json->order;
    }

    function getFolderID(): ?int
    {
        return $this->json->default_folder_id;
    }

    /**
     * Returns the first value in the given field
     * @param string|Field $field_path
     * @return null|string|bool|int|array|File
     */
    public function val($field_path, string $lang = null) //: null | string | bool | int | array | File
    {
        return $this->values($field_path, $lang)->first();
    }

    /**
     * Returns all the values in the given field
     * @param string|Field $field_path
     * @return Collection<int,int|float|string|OpFile|array>
     */
    public function values($field_path, string $lang = null, Field &$field = null): Collection
    {
        // Try to return values in default language or using the fallback language if set
        if (is_null($lang)) {
            $ret = $this->values($field_path, $this->api->schema->lang, $field);
            if ($ret->isEmpty() && $this->api->schema->getFallbackLang()) {
                $ret = $this->values($field_path, $this->api->schema->getFallbackLang(), $field);
            }
            return $ret;
        }

        if ($field_path instanceof Field) {
            $field_path = $field_path->name;
        }
        if ($field_path == '_id') return collect([$this->id]);
        if ($field_path == '_resource_id') return collect([$this->json->resource_id]);
        if ($field_path == '_created_at') return collect([$this->json->created_at]);
        if ($field_path == '_updated_at') return collect([$this->json->updated_at]);

        if (is_string($field_path)) {
            $field_path = explode('.', $field_path);
        }
        if (!is_array($field_path)) {
            $field_path = [$field_path];
        }

        if (count($field_path) > 1) {
            $related = $this->rel(array_slice($field_path, 0, -1));
            return $related->flatMap(fn (Thing $rel) => $rel->values(collect($field_path)->last(), $lang, $field))->unique();
        }

        $path = $this->resource()->resolveFieldPath($field_path);
        /** @var Field */
        $field = $path->last();

        $codename = $field->identifier($lang);
        $values = $this->json->fields->{$codename} ?? $this->json->rel_ids->{$codename} ?? null;
        if (is_null($values)) {
            return collect([]);
        }
        if (!$field->is_multiple) {
            $values = [$values];
        }
        $values = collect($values);

        if ($field->isMedia()) {
            $values->transform(function ($v) {
                return new File($this->api, $v);
            });
        }

        return $values;
    }

    /**
     * @return Collection<int,Thing>
     */
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


    /**
     * Returns the first file in the given field
     * @param string|Field $field_path
     * @return ?File
     */
    public function file($field_path, string $lang = null): ?File
    {
        $ret = $this->val($field_path, $lang);
        if ($ret instanceof File) {
            return $ret;
        }
        return null;
    }
    /**
     * Returns all the files in the given field
     * @param string|Field $field_path
     * @return Collection<int,File>
     */
    public function files($field_path, string $lang = null): Collection
    {
        $ret = $this->values($field_path, $lang, $field);
        if (!$field->isMedia()) return collect();
        return $ret;
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

    function jsonSerialize(): mixed
    {
        return $this->json;
    }
}
