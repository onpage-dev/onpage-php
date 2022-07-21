<?php

namespace OnPage;

use Illuminate\Support\Collection;
use OnPage\Exceptions\FieldNotFound;
use OnPage\Exceptions\GenericException;

class ThingEditor
{
    private ?int $id;
    public array $fields = [];
    private array $relations = [];
    private DataWriter $updater;
    private ?array $langs = null;

    /**
     * @param string[]|string|null $langs
     */
    function __construct(DataWriter $updater, int $id = null, $langs = null)
    {
        $this->id = $id;
        $this->updater = $updater;
        if (is_string($langs)) $langs = [$langs];
        $this->langs = $langs;
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
    /**
     * @param array|Collection $values
     */
    function setValues(string $field_name, $values, $lang = null, bool $append = false)
    {
        if (is_object($values)) $values = $values->all();

        $field = $this->resource()->field($field_name);
        if (!$field) throw FieldNotFound::from($field_name);

        if ($field->is_translatable && !$lang) {
            $lang = $this->langs[0] ?? $this->updater->schema()->lang;
        }
        foreach ($values as $i => $v) {
            if ($v instanceof File) {
                $values[$i] = [
                    'token' => $v->token,
                    'name' => $v->name,
                ];
            }
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
                    if ($value instanceof File) {
                        $value = [
                            'name' => $value->name,
                            'token' => $value->token,
                        ];
                    }
                    $fields[$field][] = ['lang' => $lang ?: null, 'value' => $value];
                }
            }
        }
        return [
            'id' => $this->id,
            'langs' => $this->langs,
            'fields' => $fields,
            'relations' => $this->relations,
        ];
    }
    function save()
    {
        return $this->updater->save();
    }
    function copyFromThing(Thing $th, array $limit_langs = null): Self
    {
        foreach ($this->updater->resource()->fields()->whereNotIn('type', ['relation']) as $f) {
            $remote_field = $th->resource()->field($f->name);
            if (!$remote_field) continue;
            $langs = $f->is_translatable ? $limit_langs ?? $this->langs ?? $this->updater->schema()->langs : [null];

            foreach ($langs as $l) {
                $this->setValues($f->name, $th->values($f->name, $l), $l);
            }
        }
        return $this;
    }
}
