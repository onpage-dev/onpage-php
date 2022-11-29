<?php

namespace OnPage;

use OnPage\Exceptions\FieldNotFound;
use OnPage\Exceptions\GenericException;

class DataWriter
{
    private AbstractApi $api;
    private Resource $resource;
    private array $edits = [];
    private bool $ignore_invalid_urls = false;
    private bool $queue_pdf_generators = false;

    function __construct(AbstractApi $api, Resource $resource)
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
    function createThing(string $local_key = null, $langs = null): ThingEditor
    {
        if (is_null($local_key)) $local_key = uniqid();
        $local_key = 'thing_' . $local_key;
        if (!isset($this->edits[$local_key])) $this->edits[$local_key] = new ThingEditor($this, null, $langs);
        return $this->edits[$local_key];
    }
    function forThing(int $id, $langs = null): ThingEditor
    {
        return $this->updateThing($id, $langs);
    }
    function updateThing(int $id, $langs = null): ThingEditor
    {
        if (!isset($this->edits[$id])) {
            $this->edits[$id] = new ThingEditor($this, $id, $langs);
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

    function ignoreInvalidUrls(bool $ignore = true): Self
    {
        $this->ignore_invalid_urls = $ignore;
        return $this;
    }
    function queuePdfGenerators(bool $queue = true): Self
    {
        $this->queue_pdf_generators = $queue;
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
                    'queue_pdf_generators' => $this->queue_pdf_generators,
                ],
            ];
            foreach ($chunk as $edit) {
                $req['things'][] = $edit->toArray();
            }
            try {
                $res = $this->api->post('things/bulk', $req);
                $ret = array_merge($ret, $res);
            } catch (\GuzzleHttp\Exception\ClientException $e) {
                $res = json_decode($e->getResponse()->getBody());
                throw new GenericException($res->message);
            }
        }
        $this->edits = [];
        return $ret;
    }
}
