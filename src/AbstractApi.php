<?php

namespace OnPage;

abstract class AbstractApi
{
    protected $is_user_mode;
    protected $domain;
    protected $token;
    protected int $req_count = 0;
    public string $thumbnail_format = 'png';
    public bool $download_thing_labels = false;


    function loadSchema(int $schema_id = null): Schema
    {
        return new Schema($this, $schema_id ? $this->get("schemas/$schema_id") : $this->get('schema'));
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

    public function getRequestCount(): int
    {
        return $this->req_count;
    }

    function resetRequestCount()
    {
        $this->req_count = 0;
    }

    function storageLink(string $token, string $name = null, bool $force_download = false): string
    {
        $url = "https://storage.$this->domain/$token";
        if ($name) {
            $url .= '/' . rawurlencode($name);
        }
        $options = [];
        if ($force_download) {
            $options['download'] = 1;
        }
        if (count($options)) {
            $url .= '?' . http_build_query($options);
        }
        return $url;
    }
}
