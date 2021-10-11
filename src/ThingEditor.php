<?php

namespace OnPage;

use OnPage\Exceptions\FieldNotFound;
use OnPage\Exceptions\GenericException;

class ThingEditor
{
    private ?int $id;
    private array $fields = [];
    private array $relations = [];
    private DataWriter $updater;

    function __construct(DataWriter $updater, int $id = null)
    {
        $this->id = $id;
        $this->updater = $updater;
    }

    private function resource(): Resource
    {
        return $this->updater->resource();
    }

    function set(string $field_name, $values, $lang = null): ThingEditor
    {
        $field = $this->resource()->field($field_name);
        if (!$field) throw FieldNotFound::from($field_name);

        if (!$field->is_multiple) {
            $values = [$values];
        } elseif (!is_array($values)) {
            throw new GenericException("The field $field->name is multiple, therefore you must pass an array of values");
        }
        $this->fields[$field_name][$lang] = array_merge($this->fields[$field_name][$lang] ?? [], $values);
        return $this;
    }

    function toArray(): array
    {
        $fields = [];
        foreach ($this->fields as $field => $values) {
            foreach ($values as $lang => $values) {
                foreach ($values as $value) {
                    $fields[$field][] = ['lang' => $lang ?: null, 'value' => $value];
                }
            }
        }
        return [
            'id' => $this->id,
            'fields' => $fields,
            'relations' => $this->relations,
        ];
    }
    function save()
    {
        return $this->updater->save();
    }
}
