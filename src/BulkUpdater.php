<?php

namespace OnPage;

use OnPage\Exceptions\FieldNotFound;

class BulkUpdater
{
    private Api $api;
    private Resource $resource;
    private array $edits = [];
    private $creations = 0;
    function __construct(Api $api, Resource $resource)
    {
        $this->api = $api;
        $this->resource = $resource;
    }
    function resource(): Resource
    {
        return $this->resource;
    }
    function createThing(): ThingEditor
    {
        return $this->edits[--$this->creations] = new ThingEditor($this);
    }
    function forThing(int $id): ThingEditor
    {
        if (!isset($this->edits[$id])) {
            $this->edits[$id] = new ThingEditor($this, $id);
        }
        return $this->edits[$id];
    }
    function resolveField($field): Field
    {
        $ret = $this->resource->field($field);
        if (!$ret) {
            throw FieldNotFound::from($field);
        }
        return $ret;
    }
    function save()
    {
        $req = [
            'resource' => $this->resource->name,
            'things' => [],
        ];
        foreach ($this->edits as $edit) {
            $req['things'][] = $edit->toArray();
        }
        $res = $this->api->post('things/bulk', $req);
        $this->edits = [];
        return array_column($res, 'id');
    }
}
