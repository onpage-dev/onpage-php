<?php

namespace OnPage;

use GuzzleHttp\Client;

class Api
{
    private Client $http;
    private string $api_url;
    public Schema $schema;
    private int $req_count = 0;
    private bool $auto_save = true;
    private array $pending_writes = [];

    function __construct(string $endpoint, string $token)
    {
        if (!preg_match('/^https?:/', $endpoint)) {
            $endpoint = "https://$endpoint.onpage.it/api/";
        }
        // Remove final /
        $endpoint = preg_replace('/\\/+$/', '', $endpoint);

        $this->api_url = $endpoint;
        $this->http = new Client([
            'timeout' => 60000,
            'base_uri' => "{$this->api_url}/view/{$token}/",
        ]);
        $this->loadSchema();
    }

    function loadSchema()
    {
        $this->schema = new Schema($this, $this->get('schema'));
    }

    function get(string $endpoint, array $params = [])
    {
        $params['_method'] = 'get';
        return $this->post($endpoint, $params);
    }
    function post(string $endpoint, array $data = [])
    {
        $req = [];
        if ($this->containsFiles($data)) {
            $req['multipart'] = $this->toFormData($data);
        } else {
            $req['json'] = $data;
        }
        $this->req_count++;
        $res = $this->http->request('POST', $endpoint, $req);
        return $this->handleResponse($res);
    }

    private function containsFiles(array $data)
    {
        foreach ($data as $value) {
            if (is_object($value) && $value instanceof File) {
                return true;
            } elseif (is_array($value)) {
                if ($this->containsFiles($value)) {
                    return true;
                }
            }
        }
        return false;
    }


    private function toFormData(array $data, $namespace = '')
    {
        $ret = [];
        foreach ($data as $key => $value) {
            $ns = $namespace ? "{$namespace}[{$key}]" : $key;
            if (is_null($value)) {
                // Do nothing
            } elseif (is_object($value) && $value instanceof File) {
                $ret[] = [
                    'name' => $ns,
                    'filename' => basename($value->path),
                    'contents' => fopen($value->path, 'r'),
                ];
            } elseif (is_scalar($value)) {
                $ret[] = [
                    'name' => $ns,
                    'contents' => $value,
                ];
            } else {
                $data = $this->toFormData($value, $ns);
                $ret = array_merge($ret, $data);
            }
        }
        return $ret;
    }

    private function handleResponse(\Psr\Http\Message\ResponseInterface $res)
    {
        $code = $res->getStatusCode();
        switch ($code) {
            case 200:
            case 201:
                return json_decode($res->getBody());
            default:
                throw new Exceptions\ApiError("Status code [{$code}]");
        }
    }

    function query(string $resource): QueryBuilder
    {
        return new QueryBuilder($this, $resource);
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

        $base_uri = $this->http->getConfig('base_uri');
        $url = "{$this->api_url}/storage/$token";
        if ($name) {
            $url .= '?' . http_build_query([
                'name' => $name,
            ]);
        }
        return $url;
    }
}
