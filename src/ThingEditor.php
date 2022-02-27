<?php

namespace OnPage;

use Illuminate\Support\Collection;
use OnPage\Exceptions\FieldNotFound;
use OnPage\Exceptions\GenericException;

class ThingEditor
{
    private ?int $id;
    private array $fields = [];
    private array $relations = [];
    private DataWriter $updater;

    function __construct(DataWriter $updater, int $id = null, string $lang = null)
    {
        $this->id = $id;
        $this->updater = $updater;
        $this->lang = $lang;
    }

    private function resource(): Resource
    {
        return $this->updater->resource();
    }

    function hasData(): bool
    {
        return count($this->fields) || count($this->relations);
    }

    // Alias for setValues() with only one value
    function set(string $field_name, $value, $lang = null, bool $append = false): ThingEditor
    {
        return $this->setValues($field_name, [$value], $lang, $append);
    }

    // Set all values for specified field-lang combination
    function setValues(string $field_name, array|Collection $values, $lang = null, bool $append = false)
    {
        if (is_object($values)) $values = $values->all();
        
        $field = $this->resource()->field($field_name);
        if (!$field) throw FieldNotFound::from($field_name);

        if ($field->is_translatable && !$lang) {
            $lang = $this->lang ?? $this->updater->schema()->lang;
        }

        if ($append) {
            $values = array_merge($this->fields[$field_name][$lang] ?? [], $values);
        }
        $this->fields[$field_name][$lang] = $values;
        return $this;
    }

    function setRel(string $field_name, array $values)
    {
        $this->relations[$field_name] = $values;
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
            'lang' => $this->lang,
            'fields' => $fields,
            'relations' => $this->relations,
        ];
    }
    function save()
    {
        return $this->updater->save();
    }
}
