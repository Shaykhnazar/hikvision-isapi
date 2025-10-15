<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Shaykhnazar\HikvisionIsapi\Client\Contracts\HttpClientInterface;
use Shaykhnazar\HikvisionIsapi\Exceptions\HikvisionException;

class HttpClient implements HttpClientInterface
{
    private Client $client;

    public function __construct()
    {
        $this->client = new Client();
    }

    public function get(string $uri, array $options = []): array
    {
        return $this->request('GET', $uri, $options);
    }

    public function post(string $uri, array $data = [], array $options = []): array
    {
        $format = $options['_format'] ?? 'json';
        unset($options['_format']);

        if ($format === 'xml') {
            $options['body'] = $this->arrayToXml($data);
        } else {
            $options['json'] = $data;
        }

        return $this->request('POST', $uri, $options);
    }

    public function put(string $uri, array $data = [], array $options = []): array
    {
        $format = $options['_format'] ?? 'json';
        unset($options['_format']);

        if ($format === 'xml') {
            $options['body'] = $this->arrayToXml($data);
        } else {
            $options['json'] = $data;
        }

        return $this->request('PUT', $uri, $options);
    }

    public function delete(string $uri, array $options = []): array
    {
        return $this->request('DELETE', $uri, $options);
    }

    public function postMultipart(string $uri, array $multipart = [], array $options = []): array
    {
        $options['multipart'] = $multipart;
        return $this->request('POST', $uri, $options);
    }

    private function request(string $method, string $uri, array $options): array
    {
        try {
            $response = $this->client->request($method, $uri, $options);

            $body = $response->getBody()->getContents();
            $contentType = $response->getHeader('Content-Type')[0] ?? '';

            // Parse JSON responses
            if (str_contains($contentType, 'application/json')) {
                return json_decode($body, true) ?? [];
            }

            // Parse XML responses
            if (str_contains($contentType, 'application/xml') || str_contains($contentType, 'text/xml')) {
                return $this->xmlToArray($body);
            }

            // Fallback: return raw body
            return ['raw' => $body];
        } catch (GuzzleException $e) {
            $errorMessage = "HTTP request failed: {$e->getMessage()}";

            // Try to get response body for better error messages
            if (method_exists($e, 'getResponse') && $e->getResponse()) {
                $responseBody = (string) $e->getResponse()->getBody();
                if (!empty($responseBody)) {
                    $errorMessage .= "\nResponse: {$responseBody}";
                }
            }

            throw new HikvisionException(
                $errorMessage,
                $e->getCode(),
                $e
            );
        }
    }

    /**
     * Convert array to XML string for Hikvision ISAPI
     */
    private function arrayToXml(array $data, string $rootElement = 'UserInfo'): string
    {
        $xml = new \SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><{$rootElement} version=\"2.0\" xmlns=\"http://www.isapi.org/ver20/XMLSchema\"></{$rootElement}>");

        $this->arrayToXmlRecursive($data, $xml);

        return $xml->asXML();
    }

    /**
     * Recursively convert array to XML
     */
    private function arrayToXmlRecursive(array $data, \SimpleXMLElement $xml): void
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $child = $xml->addChild($key);
                $this->arrayToXmlRecursive($value, $child);
            } else {
                // Convert boolean to string
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                $xml->addChild($key, htmlspecialchars((string)$value));
            }
        }
    }

    /**
     * Convert XML string to array
     */
    private function xmlToArray(string $xml): array
    {
        $xmlObj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);

        if ($xmlObj === false) {
            return ['raw' => $xml];
        }

        return json_decode(json_encode($xmlObj), true) ?? ['raw' => $xml];
    }
}
