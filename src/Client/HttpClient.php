<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Client;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Shaykhnazar\HikvisionIsapi\Client\Contracts\HttpClientInterface;
use Shaykhnazar\HikvisionIsapi\Exceptions\AuthenticationException;
use Shaykhnazar\HikvisionIsapi\Exceptions\DeviceBusyException;
use Shaykhnazar\HikvisionIsapi\Exceptions\DeviceUnreachableException;
use Shaykhnazar\HikvisionIsapi\Exceptions\HikvisionException;

class HttpClient implements HttpClientInterface
{
    private Client $client;

    /**
     * @param  Client|null  $client  Optional pre-configured Guzzle client. Useful for
     *                               injecting custom middleware, retries or a test handler.
     */
    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client;
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

    public function putMultipart(string $uri, array $multipart = [], array $options = []): array
    {
        $options['multipart'] = $multipart;

        return $this->request('PUT', $uri, $options);
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
            throw $this->classify($e);
        }
    }

    /**
     * Turn a transport failure into the most specific exception we can justify.
     *
     * Classification is deliberately based only on what is certain: whether a
     * response was received at all, and its HTTP status. Hikvision also encodes
     * detail in `subStatusCode` (unsupported capability, storage full, and so
     * on), but those strings vary by model and firmware, so they are not used
     * here until they can be confirmed against real hardware.
     */
    private function classify(GuzzleException $e): HikvisionException
    {
        $message = "HTTP request failed: {$e->getMessage()}";

        if ($e instanceof ConnectException) {
            return new DeviceUnreachableException($message, $e->getCode(), $e);
        }

        $response = $e instanceof RequestException ? $e->getResponse() : null;

        if ($response === null) {
            return new HikvisionException($message, $e->getCode(), $e);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();

        if ($body !== '') {
            $message .= "\nResponse: {$body}";
        }

        if ($status === 401) {
            return new AuthenticationException($message, $e->getCode(), $e, $status, $body);
        }

        if ($status === 408 || $status === 429 || $status >= 500) {
            return new DeviceBusyException($message, $e->getCode(), $e, $status, $body);
        }

        return new HikvisionException($message, $e->getCode(), $e, $status, $body);
    }

    /**
     * Convert array to XML string for Hikvision ISAPI
     * Automatically detects root element from array structure
     */
    private function arrayToXml(array $data, ?string $rootElement = null): string
    {
        // Auto-detect root element from array keys
        if ($rootElement === null) {
            // If array has single key, use it as root element
            if (count($data) === 1) {
                $rootElement = array_key_first($data);
                $data = $data[$rootElement];
            } else {
                // Default fallback
                $rootElement = 'UserInfo';
            }
        }

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
                $xml->addChild($key, htmlspecialchars((string) $value));
            }
        }
    }

    /**
     * Convert XML string to array
     */
    private function xmlToArray(string $xml): array
    {
        // Devices occasionally emit truncated or malformed XML. Collect libxml
        // errors internally so a bad payload never raises a PHP warning.
        $previous = libxml_use_internal_errors(true);

        try {
            $xmlObj = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xmlObj === false) {
            return ['raw' => $xml];
        }

        return json_decode(json_encode($xmlObj), true) ?? ['raw' => $xml];
    }
}
