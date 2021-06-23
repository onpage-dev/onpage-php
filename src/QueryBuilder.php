<?php

namespace OnPage;

class QueryBuilder
{
    private string $resource;
    private array $filters = [];
    private ?array $related_to = null;
    private Api $api;

    function __construct(Api $api, string $resource)
    {
        $this->api = $api;
        $this->resource = $resource;
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

    private function build(string $return): array
    {
        $data = [
            'resource' => $this->resource,
            'filters' => $this->filters,
            'fields' => ['+'],
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

    function relatedTo(Field $field, int $thing_id) : QueryBuilder {
        $this->related_to = [
            'field_id' => $field->id,
            'thing_id' => $thing_id,
        ];
        return $this;
    }
}
