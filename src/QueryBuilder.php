<?php

namespace OnPage;

use Illuminate\Support\Collection;

class QueryBuilder
{
    private Resource $resource;
    private array $filters = [];
    private FieldLoader $field_loader;
    private ?array $related_to = null;
    private Api $api;
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(Api $api, Resource $resource)
    {
        $this->api = $api;
        $this->resource = $resource;
        $this->field_loader = new FieldLoader();
    }

    function loadFields(array $fields): QueryBuilder
    {
        $this->field_loader->loadFields($fields);
        return $this;
    }

    public function all(): ThingCollection
    {
        return ThingCollection::fromResponse($this->api, $this->api->get('things', $this->build('list')));
    }
    public function ids(): Collection
    {
        return collect($this->api->get('things', $this->build('ids')));
    }
    public function map(string $keyfield, string $valuefield = '_id', $lang = null): array
    {
        $this->loadFields([$keyfield, $valuefield]);
        return $this->all()->mapWithKeys(function (Thing $thing)  use ($keyfield, $valuefield, $lang) {
            $key = $thing->values($keyfield, $lang)[0] ?? '';
            $value = $thing->values($valuefield, $lang)[0] ?? '';
            return [
                $key => $value
            ];
        })->all();
    }
    public function first(): ?Thing
    {
        $res = $this->api->get('things', $this->build('first'));
        return $res ? new Thing($this->api, $res) : null;
    }
    public function delete(): ?array
    {
        $req = $this->build('delete');
        $res = $this->api->delete('things', $req);
        return $res;
    }
    public function with($relations)
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }
        foreach ($relations as $rel) {
            $path = explode('.', $rel);
            $loader = $this->field_loader;

            foreach ($path as $rel_name) {
                $loader = $loader->setRelation($rel_name);
            }
        }
        return $this;
    }
    private function build(string $return): array
    {
        $data = [
            'resource' => $this->resource->name,
            'filters' => $this->filters,
            'fields' => $this->field_loader->encode(),
            'return' => $return,
            'options' => [
                'no_labels' => true,
                'hyper_compact' => true,
                'use_field_names' => true,
            ],
        ];

        if ($this->related_to) {
            $data['related_to'] = $this->related_to;
        }

        if ($this->limit) {
            $data['limit'] = $this->limit;
        }
        if ($this->offset) {
            $data['offset'] = $this->offset;
        }

        return $data;
    }

    function offset(int $offset)
    {
        $this->offset = $offset;
        return $this;
    }
    function limit(int $limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function relatedTo(Field $field, int $thing_id): QueryBuilder
    {
        $this->related_to = [
            'field_id' => $field->id,
            'thing_id' => $thing_id,
        ];
        return $this;
    }

    public function where(string $field, $op, $value = null)
    {
        if (is_null($value)) {
            $value = $op;
            $op = '=';
        }
        $filter = [$field, $op, $value];
        $this->filters[] = $filter;
        return $this;
    }
}
