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
    private bool $ignore_invalid_urls = false;

    function __construct(Api $api, Resource $resource)
    {
        $this->api = $api;
        $this->resource = $resource;
    }
    function resource(): Resource
    {
        return $this->resource;
    }
    function schema(): Schema
    {
        return $this->api->schema;
    }
    function createThing(string $id): ThingEditor
    {
        $id = md5($id);
        if (!isset($this->edits[$id])) $this->edits[$id] = new ThingEditor($this);

        return $this->edits[$id];
    }
    function forThing(int $id, string $lang = null): ThingEditor
    {
        if (!isset($this->edits[$id])) {
            $this->edits[$id] = new ThingEditor($this, $id, $lang);
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

    function ignoreInvalidUrls(bool $ignore = true): DataWriter
    {
        $this->ignore_invalid_urls = $ignore;
        return $this;
    }

    /** @return int[] */
    function save(): array
    {
        $edits = array_filter($this->edits, function (ThingEditor $edit) {
            return $edit->hasData();
        });
        $ret = [];
        foreach (array_chunk($edits, 1000) as $chunk) {
            $req = [
                'resource' => $this->resource->name,
                'things' => [],
                'options' => [
                    'ignore_invalid_urls' => $this->ignore_invalid_urls,
                ],
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
