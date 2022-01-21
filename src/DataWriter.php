<?php

namespace OnPage;

use OnPage\Exceptions\FieldNotFound;
use OnPage\Exceptions\GenericException;

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
    function createThing(string $id): ThingEditor
    {
        $id = md5($id);
        if (!isset($this->edits[$id])) $this->edits[$id] = new ThingEditor($this);

        return $this->edits[$id];
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

    /** @return int[] */
    function save(): array
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
            try {
                $res = $this->api->post('things/bulk', $req);
                
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $res = json_decode($e->getResponse()->getBody());
                throw new GenericException($res->message);
            }
            $ret = array_merge($ret, $res);
        }
        $this->edits = [];
        return $ret;
    }
}
