<?php

namespace OnPage;

use OnPage\Exceptions\FieldNotFound;

class DataWriter
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
        $ret = [];
        foreach (array_chunk($this->edits, 1000) as $chunk) {
            $req = [
                'resource' => $this->resource->name,
                'things' => [],
            ];
            foreach ($chunk as $edit) {
                $req['things'][] = $edit->toArray();
            }
            $res = $this->api->post('things/bulk', $req);
            $ret = array_merge($ret, array_column($res, 'id'));
        }
        $this->edits = [];
        return $ret;
    }
}
