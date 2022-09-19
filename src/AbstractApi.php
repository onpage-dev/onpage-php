<?php

namespace OnPage;

abstract class AbstractApi
{
    public Schema $schema;
    protected int $req_count = 0;
    public $allow_dynamic_relations = false;
    public string $thumbnail_format = 'png';
    public bool $download_thing_labels = true;
    protected string $api_url = 'https://app.onpage.it/api/';


    function loadSchema(): Schema
    {
        $this->schema = new Schema($this, $this->get('schema'));
        return $this->schema;
    }

    function get(string $endpoint, array $params = [])
    {
        return $this->request('get', $endpoint, $params);
    }

    function delete(string $endpoint, array $params = [])
    {
        return $this->request('delete', $endpoint, $params);
    }
    function post(string $endpoint, array $data = [])
    {
        return $this->request('post', $endpoint, $data);
    }

    abstract function request(string $method, string $endpoint, array $data = []);

    function query(string $resource): QueryBuilder
    {
        return $this->schema->query($resource);
    }

    public function getRequestCount(): int
    {
        return $this->req_count;
    }

    function resetRequestCount()
    {
        $this->req_count = 0;
    }
    function storageLink(string $token, string $name = null): string
    {
        $url = "{$this->api_url}/storage/$token";
        if ($name) {
            $url .= '/' . rawurlencode($name);
        }
        return $url;
    }

    /**
     * Dumps a csv containing information about
     * the used fields
     */
    function dumpUsedFields(string $csv_path)
    {
        $file = fopen($csv_path, 'wb');

        fputcsv($file, [
            'Resource',
            'Resource name',
            'Field',
            'Field name',
            'Field type',
        ]);

        foreach ($this->schema->resources() as $res) {
            $used = false;
            foreach ($res->fields() as $field) {
                if (!$field->hasBeenUsed()) continue;
                $used = true;
                fputcsv($file, [
                    $res->label,
                    $res->name,
                    $field->label,
                    $field->name,
                    $field->type,
                ]);
            }
            if ($used) {
                fputcsv($file, []);
            }
        }

        fclose($file);
    }
}
