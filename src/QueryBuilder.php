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
    private string $trash_status = 'active'; // active|deleted|any;

    public function __construct(AbstractApi $api, Resource $resource)
    {
        $this->api = $api;
        $this->resource = $resource;
        $this->field_loader = new FieldLoader();
    }

    function loadFields(array $fields, bool $append = false): QueryBuilder
    {
        $this->field_loader->loadFields($fields, $append);
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
     * @return Collection<int,Thing>
     */
    public function count(): int
    {
        return $this->api->get('things', $this->build('count'));
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

    /** @return Collection<mixed> */
    public function pluck($field): Collection
    {
        $data = $this->build('pluck');
        $data['field'] = $field;
        return collect($this->api->get('things', $data));
    }

    public function first(): ?Thing
    {
        $res = $this->api->get('things', $this->build('first'));
        return $res ? new Thing($this->api, $res) : null;
    }
    /**
     * @param array|int $config
     * @param string $name
     * @return string the link to the generated excel file
     */
    public function downloadTable($config, string $name): string
    {
        $options = [
            'table_config' => $config,
        ];
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        if ($ext == 'csv') {
            $options['format'] = 'csv';
        } elseif (in_array($ext, ['xls', 'xlsx'])) {
            $options['format'] = 'excel';
        } else {
            throw new \Error("File name must end with .csv or .xlsx");
        }
        $file_token = $this->api->get('things', $this->build('table-view', $options));
        return $this->api->storageLink($file_token, $name);
    }
    public function downloadTableAsJson($config): array
    {
        $options = [
            'table_config' => $config,
            'format' => 'json',
        ];
        return $this->api->get('things', $this->build('table-view', $options));
    }
    public function delete(bool $forever = false): ?array
    {
        $req = $this->build('delete');
        if ($forever) $req['forever'] = true;
        $res = $this->api->delete('things', $req);
        return $res;
    }
    /** @param string|array $rel */
    public function filterRelation($rel, callable $subquery)
    {
        $loader = $this->field_loader->getRelation($rel);
        $qb = new QueryBuilder($this->api, $loader->relation->relatedResource());
        $subquery($qb);
        $loader->filters[] = $qb->filters;

        if (!is_null($qb->getLimit())) {
            $loader->limit = $qb->getLimit();
        }
        if (!is_null($qb->getOffset())) {
            $loader->offset = $qb->getOffset();
        }

        return $this;
    }

    /** @param string|array $rel */
    public function loadRelationFields($rel, array $fields, $append = false)
    {
        $loader = $this->field_loader->getRelation($rel);
        $loader->loadFields($fields, $append);
        return $this;
    }

    public function with($relations)
    {
        if (is_string($relations)) {
            $relations = [$relations];
        }
        foreach ($relations as $rel) {
            $path = explode('.', $rel);
            $loader = $this->field_loader;

            $prev_field = null;
            foreach ($path as $rel_name) {
                $field = null;
                if ($prev_field) $field = $prev_field->relatedResource()->field($rel_name);
                else $field = $this->resource->field($rel_name);
                if (!$field) throw new \Error("Cannot find $rel_name to preload");
                $loader = $loader->setRelation($field);
                $prev_field = $field;
            }
        }
        return $this;
    }
    private function build(string $return, array $options = []): array
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
                'status' => $this->trash_status,
            ],
        ] + $options;

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

    function getLimit()
    {
        return $this->limit;
    }
    function getOffset()
    {
        return $this->offset;
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
    /** @param array|Collection $values */
    public function whereIn(string $field, $values)
    {
        if ($values instanceof Collection) {
            $values = $values->all();
        }
        return $this->where($field, 'in', $values);
    }
    public function whereEmpty(string $field)
    {
        $this->filters[] = [$field, 'empty', ''];
        return $this;
    }
    public function whereNotEmpty(string $field)
    {
        $this->filters[] = [$field, 'not_empty', ''];
        return $this;
    }
    public function whereHas(string $field, callable $subquery = null, string $operator = '>', int $value = 0)
    {
        $fields = explode('.', $field);
        $field = array_shift($fields);

        $f = $this->resource->field($field);
        if (!$f) throw new \Error(
            "Cannot find relation $field"
        );
        if ($f->type != 'relation') {
            throw new \Error(
                "Cannot use whereHas on field {$f->name} with type {$f->type}"
            );
        }

        $query = new QueryBuilder($this->api, $f->relatedResource());
        if (count($fields)) {
            $query->whereHas(implode('.', $fields), $subquery, $operator, $value);
        } else {
            if ($subquery) $subquery($query);
        }

        $clause = [
            'type' => 'group',
            'resource_id' => $this->resource->id,
            'relation' => [
                'field' => $field,
                'operator' => count($fields) ? '>' : $operator,
                'value' => count($fields) ? '0' : $value,
            ],
            'children' => $query->filters,
        ];
        $this->filters[] = $clause;
        return $this;
    }

    public function find(int $id): ?Thing
    {
        return $this->where("_id", $id)->first();
    }

    public function withStatus(string $status)
    {
        $this->trash_status = $status;
        return $this;
    }
    public function isActiveStatus()
    {
        return $this->withStatus('active');
    }
    public function isDeletedStatus()
    {
        return $this->withStatus('deleted');
    }
    public function isAnyStatus()
    {
        return $this->withStatus('any');
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
