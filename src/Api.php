<?php

namespace OnPage;

use GuzzleHttp\Client;

class Api extends AbstractApi
{
    private Client $http;

    function __construct(string $endpoint, string $token, float $timeout = 60000)
    {
        if (!preg_match('/^https?:/', $endpoint)) {
            $endpoint = "https://$endpoint.onpage.it/api/";
        }
        // Remove final /
        $endpoint = preg_replace('/\\/+$/', '', $endpoint);

        $this->api_url = $endpoint;
        $this->http = new Client([
            'timeout' => $timeout,
            'base_uri' => "{$this->api_url}/view/{$token}/",
            'headers' => ['Accept-Encoding' => 'gzip'],
        ]);
        $this->loadSchema();
    }

    function request(string $method, string $endpoint, array $data = [])
    {
        $data['_method'] = $method;
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
            if (is_object($value) && $value instanceof FileUpload) {
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
            } elseif (is_object($value) && $value instanceof FileUpload) {
                $ret[] = [
                    'name' => $ns,
                    'filename' => basename($value->path),
                    'contents' => fopen($value->path, 'r'),
                ];
            } elseif (is_object($value) && $value instanceof File) {
                $ret[] = [
                    'name' => "{$ns}[token]",
                    'contents' => $value->token,
                ];
                $ret[] = [
                    'name' => "{$ns}[name]",
                    'contents' => $value->name,
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
}
