<?php

namespace OnPage;

use Illuminate\Support\Collection;

class QueryBuilder
{
    private Resource $resource;
    private array $filters = [];
    private FieldLoader $field_loader;
    private ?array $related_to = null;
    private AbstractApi $api;
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(AbstractApi $api, Resource $resource)
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

    /**
     * @return Collection<int,Thing>
     */
    public function get(int $chunk_size = 0): ThingCollection
    {
        return $this->all($chunk_size);
    }

    /**
     * @return Collection<int,Thing>
     */
    public function all(int $chunk_size = 0): ThingCollection
    {
        if ($chunk_size > 0) {
            $things = [];
            $this->cursor(function ($thing) use (&$things) {
                $things[] = $thing;
            }, $chunk_size);
            return new ThingCollection($things);
        } else {
            return ThingCollection::fromResponse($this->api, $this->api->get('things', $this->build('list')));
        }
    }

    /**
     * Calls the given closure passing one thing at a time
     * the things are loaded one chunk at a time to reduce total memory
     * and reduce latency
     * @return Collection the return value of each closure call
     */
    public function cursor(callable $callback, int $request_size = 100): Collection
    {
        $ids = $this->ids();
        $done = 0;
        $total = $ids->count();
        return $ids->chunk($request_size)->flatMap(function (Collection $id_chunk) use ($callback, &$done, $total) {
            $query = clone $this;
            $query->filters = [
                ['_id', 'in', $id_chunk->values()->all()],
            ];
            $thing_chunk = $query->all()->keyBy('id');
            return $id_chunk
                ->map(fn (int $id) => $thing_chunk[$id])
                ->filter()
                ->map(function (Thing $thing) use ($callback, &$done, $total) {
                    $ret = $callback($thing, $done, $total);
                    $done += 1;
                    return $ret;
                });
        });
    }

    /** @return Collection<int> */
    public function ids(): Collection
    {
        return collect($this->api->get('things', $this->build('ids')));
    }
    public function map(string $keyfield, string $valuefield = '_id', $lang = null, int $chunk_size = 0): array
    {
        $this->loadFields([$keyfield, $valuefield]);
        return $this->all($chunk_size)->mapWithKeys(function (Thing $thing)  use ($keyfield, $valuefield, $lang) {
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
                'no_labels' => !$this->api->download_thing_labels,
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

    public function find(int $id): ?Thing
    {
        return $this->where("_id", $id)->first();
    }

    function whereOneOf(callable $fn)
    {
        $builder = new QueryBuilder($this->api, $this->resource);
        $fn($builder);
        if (!empty($builder->filters)) {
            $this->filters[] = [
                '_or',
                ...$builder->filters,
            ];
        }
        return $this;
    }
}
