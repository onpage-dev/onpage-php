<?php

namespace OnPage;

use Illuminate\Support\Collection;

class Schema
{
    public $id;
    public $label;
    public AbstractApi $api;
    private array $id_to_resource;
    private array $id_to_field;
    private array $name_to_resource;
    private array $resources;
    public array $langs;
    private ?string $fallback_lang = null;
    public string $lang;
    public $allow_dynamic_relations = false;

    public function __construct(AbstractApi $api, object $json)
    {
        $this->api = $api;
        $this->id = $json->id;
        $this->label = $json->label;
        $this->langs = $json->langs;
        $this->lang = $this->langs[0];
        foreach ($json->resources as $res_json) {
            $res = new Resource($this, $res_json);
            $this->resources[] = $res;
            $this->id_to_resource[$res_json->id] = $res;
            $this->name_to_resource[$res_json->name] = $res;
            foreach ($res->fields() as $field) {
                $this->id_to_field[$field->id] = $field;
            }
        }
    }

    public function resource($id): ?Resource
    {
        if (is_numeric($id)) {
            return $this->id_to_resource[$id] ?? null;
        } else {
            return $this->name_to_resource[$id] ?? null;
        }
    }

    /** @return Collection<Resource> */
    public function resources(): Collection
    {
        return collect($this->id_to_resource);
    }

    /** @return Collection<Field> */
    function fields(): Collection
    {
        return collect($this->id_to_field);
    }

    function query(string $resource): QueryBuilder
    {
        return $this->resource($resource)->query();
    }

    function setFallbackLang(?string $lang)
    {
        $this->fallback_lang = $lang;
    }

    function getFallbackLang()
    {
        return $this->fallback_lang;
    }

    static function fromToken(string $token): self
    {
        return (new Api($token))->loadSchema();
    }

    /**
     * Dumps a csv containing information about
     * the used fields
     */
    function dumpUsedFields(string $csv_path)
    {
        $file = fopen($csv_path, 'wb');

        fputcsv($file, [
            'Resource',
            'Resource name',
            'Field',
            'Field name',
            'Field type',
        ]);

        foreach ($this->resources() as $res) {
            $used = false;
            foreach ($res->fields() as $field) {
                if (!$field->hasBeenUsed()) continue;
                $used = true;
                fputcsv($file, [
                    $res->label,
                    $res->name,
                    $field->label,
                    $field->name,
                    $field->type,
                ]);
            }
            if ($used) {
                fputcsv($file, []);
            }
        }

        fclose($file);
    }
}
