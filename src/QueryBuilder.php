<?php

namespace OnPage;

class QueryBuilder
{
    private string $resource;
    private array $filters = [];
    private FieldLoader $field_loader;
    private ?array $related_to = null;
    private Api $api;

    function __construct(Api $api, string $resource)
    {
        $this->api = $api;
        $this->resource = $resource;
        $this->field_loader = new FieldLoader();
    }

    function all(): ThingCollection
    {
        return ThingCollection::fromResponse($this->api, $this->api->get('things', $this->build('list')));
    }
    function first(): ?Thing
    {
        $res = $this->api->get('things', $this->build('first'));
        return $res ? new Thing($this->api, $res) : null;
    }
    public function with(array|string $relations)
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
            'resource' => $this->resource,
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

        return $data;
    }

    function relatedTo(Field $field, int $thing_id): QueryBuilder
    {
        $this->related_to = [
            'field_id' => $field->id,
            'thing_id' => $thing_id,
        ];
        return $this;
    }

    function where(string $field, $op, $value = null) {
        if (is_null($value)) {
            $value = $op;
            $op = '=';
        }
        $filter = [$field, $op, $value];
        $this->filters[] = $filter;
        return $this;
    }
}
