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

    private array $folders;
    private array $id_to_folder;
    private array $name_to_folder;

    private Schema $schema;
    public function __construct(Schema $schema, object $json)
    {
        $this->schema = $schema;
        $this->id = $json->id;
        $this->name = $json->name;
        $this->label = $json->label;
        $this->labels = (array) $json->labels;
        $this->setFields($json->fields);
        $this->setFolders($json->folders ?? []);
    }

    private function setFields(array $fields)
    {
        $this->fields = [];
        $this->id_to_field = [];
        $this->name_to_field = [];
        foreach ($fields as $field_json) {
            $field = new Field($this->schema, $field_json);
            $this->fields[] = $field;
            $this->id_to_field[$field_json->id] = $field;
            $this->name_to_field[$field_json->name] = $field;
        }
    }
    private function setFolders(array $folders)
    {
        $this->folders = [];
        $this->id_to_folder = [];
        $this->name_to_folder = [];
        foreach ($folders ?? [] as $folder_json) {
            $folder = new FieldFolder($this->schema, $folder_json);
            $this->folders[] = $folder;
            $this->id_to_folder[$folder_json->id] = $folder;
            $this->name_to_folder[$folder_json->name] = $folder;
        }
    }

    function getLabel(?string $lang = null): string
    {
        if (isset($this->labels[$lang])) return $this->labels[$lang];
        $lang = $this->schema->lang;
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

    public function folder($id): ?FieldFolder
    {
        $folder = null;
        if (is_numeric($id)) {
            $folder = $this->id_to_folder[$id] ?? null;
        } else {
            $folder = $this->name_to_folder[$id] ?? null;
        }
        return $folder;
    }

    /**
     * @return Collection<FieldFolder>
     */
    public function folders(): Collection
    {
        return collect($this->folders);
    }

    function writer(): DataWriter
    {
        return new DataWriter($this->schema, $this);
    }

    function query(): QueryBuilder
    {
        return new QueryBuilder($this->schema, $this);
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
