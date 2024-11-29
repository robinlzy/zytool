<?php

namespace Ziyanco\Zytool\Extends;

use GuzzleHttp\Client;

class RequestLibrary
{
    const TYPE_JSON = 1;
    const TYPE_BUILD_QUERY = 2;

    const TYPE_FORM_PARAMS = 3;

    /**
     * GET 请求
     * @param string $url
     * @param array $requestParams
     * @param array $header
     * @return array
     */
    public static function requestGetResultJsonData(string $reqUrl, array $requestParams = [], array $header = []): array
    {
        try {
            $client = new Client();
            $url = $reqUrl . "?" . http_build_query($requestParams);
            $promise = $client->requestAsync('GET', $url,
                [
                    'headers' => $header
                ]);
            $response = $promise->wait();
            $response = $response->getBody()->getContents();
            $res = json_decode($response, true);
            return $res;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse()->getBody()->getContents();
            if (!empty($body)) {
                $result = json_decode($response, true);
                return $result;
            }
            throw $e;
        }
    }

    /**
     * POST 请求
     * @param string $url
     * @param array $requestParams
     * @param array $header
     * @return array
     */
    public static function requestPostResultJsonData(string $reqUrl, array $requestParams = [], array $header = ['Content-Type' => 'application/json; charset=UTF-8',
        'Accept' => 'application/json'],                    $type = RequestLibrary::TYPE_JSON): array
    {
        try {
            $body = static::getBody($requestParams, $type);;
            $client = new Client();
            if ($header['Content-Type'] == 'application/json; charset=UTF-8') {
                $promise = $client->requestAsync('POST', $reqUrl, [
                    'body' => $body,
                    'headers' => $header
                ]);
            } elseif ($header['Content-Type'] == 'application/x-www-form-urlencoded') {
                $promise = $client->requestAsync('POST', $reqUrl, [
                    'form_params' => $body,
                    'headers' => $header
                ]);
            }

            $response = $promise->wait();
            $response = $response->getBody()->getContents();
            $result = json_decode($response, true);
            return empty($result) ? [] : $result;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse()->getBody()->getContents();
            if (!empty($body)) {
                $result = json_decode($response, true);
                return empty($result) ? [] : $result;
            }
            throw $e;
        }
    }

    private static function getBody($params, $type = RequestLibrary::TYPE_JSON)
    {
        $body = '';
        switch ($type) {
            case RequestLibrary::TYPE_JSON:
                $body = json_encode($params);
                break;
            case RequestLibrary::TYPE_BUILD_QUERY:
                $body = http_build_query($params);
                break;
            case RequestLibrary::TYPE_FORM_PARAMS:
                $body = $params;
                break;
        }
        return $body;
    }
}