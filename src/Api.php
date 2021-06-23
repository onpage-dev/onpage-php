<?php

namespace OnPage;

use GuzzleHttp\Client;

class Api
{
    private Client $http;
    public Schema $schema;

    function __construct(string $endpoint, string $token)
    {
        if (!preg_match('/^https?:/', $endpoint)) {
            $endpoint = "https://$endpoint.onpage.it/api/";
        }

        $endpoint .= "view/{$token}/";

        $this->http = new Client([
            'timeout' => 60000,
            'base_uri' => $endpoint,
        ]);

        $this->loadSchema();
    }

    function loadSchema()
    {
        $this->schema = new Schema($this, $this->get('schema'));
    }

    function get(string $endpoint, array $params = [])
    {
        // $params['_method'] = 'get';
        // return $this->post($endpoint, $params);
        $res = $this->http->request('GET', $endpoint, [
            'query' => $params,
        ]);
        return $this->handleResponse($res);
    }
    function post(string $endpoint, array $data = [])
    {
        $res = $this->http->request('POST', $endpoint, [
            'json' => $data,
        ]);
        return $this->handleResponse($res);
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
}
