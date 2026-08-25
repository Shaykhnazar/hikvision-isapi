<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Services;

use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;

/**
 * Service for managing HTTP event notifications
 * Allows configuring Hikvision devices to send events to HTTP endpoints
 */
class EventNotificationService
{
    private const ENDPOINT_HTTP_HOSTS = '/ISAPI/Event/notification/httpHosts';

    private const ENDPOINT_HTTP_HOST = '/ISAPI/Event/notification/httpHosts/%s';

    private const ENDPOINT_NOTIFICATION_CAPABILITIES = '/ISAPI/Event/notification/capabilities';

    public function __construct(
        private readonly HikvisionClient $client
    ) {}

    /**
     * Configure HTTP notification host
     * Tells the device to send events to specified URL
     *
     * @param  string  $url  Target URL to receive events (e.g., https://your-api.com/api/webhooks/hikvision/events)
     * @param  int  $id  Host ID (1-8, default 1)
     * @param  string  $protocol  Protocol type (HTTP or HTTPS)
     * @param  int  $port  Port number (default 80 for HTTP, 443 for HTTPS)
     * @param  string  $httpAuthType  Authentication type (none, basic, digest)
     * @param  string|null  $username  Username for authentication (if required)
     * @param  string|null  $password  Password for authentication (if required)
     * @param  array  $eventTypes  Array of event types to send (empty = all events)
     * @return array Response from device
     */
    public function configureHttpHost(
        string $url,
        int $id = 1,
        string $protocol = 'HTTP',
        int $port = 80,
        string $httpAuthType = 'none',
        ?string $username = null,
        ?string $password = null,
        array $eventTypes = []
    ): array {
        // Parse URL to extract path
        $urlParts = parse_url($url);
        $addressingFormatType = 'ipaddress';
        $ipAddress = $urlParts['host'] ?? '';
        $url_path = $urlParts['path'] ?? '/';

        // Detect if using hostname instead of IP
        if (!filter_var($ipAddress, FILTER_VALIDATE_IP)) {
            $addressingFormatType = 'hostname';
        }

        $data = [
            'HttpHostNotification' => [
                'id' => $id,
                'url' => $url_path,
                'protocolType' => strtoupper($protocol),
                'parameterFormatType' => 'XML',
                'addressingFormatType' => $addressingFormatType,
                'ipAddress' => $ipAddress,
                'portNo' => $port,
                'httpAuthenticationMethod' => $httpAuthType,
                'enabled' => true,
            ],
        ];

        // Add authentication if provided
        if ($httpAuthType !== 'none' && $username && $password) {
            $data['HttpHostNotification']['userName'] = $username;
            $data['HttpHostNotification']['password'] = $password;
        }

        // Add event types if specified
        if (!empty($eventTypes)) {
            // Format event types for XML conversion
            $data['HttpHostNotification']['eventList'] = [
                'eventType' => $eventTypes,
            ];
        }

        // Use PUT with XML format (event notification endpoint requires XML)
        $endpoint = sprintf(self::ENDPOINT_HTTP_HOST, $id);

        return $this->client->putXml($endpoint, $data);
    }

    /**
     * Get HTTP notification host configuration
     *
     * @param  int  $id  Host ID (1-8)
     * @return array Host configuration
     */
    public function getHttpHost(int $id = 1): array
    {
        $endpoint = sprintf(self::ENDPOINT_HTTP_HOST, $id);

        return $this->client->get($endpoint);
    }

    /**
     * Get all HTTP notification hosts
     *
     * @return array All host configurations
     */
    public function getAllHttpHosts(): array
    {
        return $this->client->get(self::ENDPOINT_HTTP_HOSTS);
    }

    /**
     * Remove HTTP notification host configuration
     *
     * @param  int  $id  Host ID (1-8)
     * @return array Response from device
     */
    public function removeHttpHost(int $id = 1): array
    {
        $endpoint = sprintf(self::ENDPOINT_HTTP_HOST, $id);

        return $this->client->delete($endpoint);
    }

    /**
     * Test HTTP notification configuration
     * Sends a test event to the configured URL
     *
     * @param  int  $id  Host ID to test (1-8)
     * @return array Response from device
     */
    public function testHttpNotification(int $id = 1): array
    {
        $endpoint = sprintf(self::ENDPOINT_HTTP_HOST, $id).'/test';

        return $this->client->post($endpoint, []);
    }

    /**
     * Get notification capabilities
     * Returns information about what notification features the device supports
     *
     * @return array Capabilities information
     */
    public function getCapabilities(): array
    {
        return $this->client->get(self::ENDPOINT_NOTIFICATION_CAPABILITIES);
    }

    /**
     * Enable HTTP notification host
     *
     * @param  int  $id  Host ID (1-8)
     * @return array Response from device
     */
    public function enableHttpHost(int $id = 1): array
    {
        $config = $this->getHttpHost($id);

        if (!isset($config['HttpHostNotification'])) {
            throw new \RuntimeException('HTTP host configuration not found');
        }

        $config['HttpHostNotification']['enabled'] = true;

        $endpoint = sprintf(self::ENDPOINT_HTTP_HOST, $id);

        return $this->client->putXml($endpoint, $config);
    }

    /**
     * Disable HTTP notification host
     *
     * @param  int  $id  Host ID (1-8)
     * @return array Response from device
     */
    public function disableHttpHost(int $id = 1): array
    {
        $config = $this->getHttpHost($id);

        if (!isset($config['HttpHostNotification'])) {
            throw new \RuntimeException('HTTP host configuration not found');
        }

        $config['HttpHostNotification']['enabled'] = false;

        $endpoint = sprintf(self::ENDPOINT_HTTP_HOST, $id);

        return $this->client->putXml($endpoint, $config);
    }

    /**
     * Configure simplified HTTP notification
     * Easier method for basic webhook setup
     *
     * @param  string  $webhookUrl  Full webhook URL (e.g., https://api.example.com/webhooks/events)
     * @param  int  $hostId  Host ID (1-8, default 1)
     * @param  array  $eventTypes  Event types to subscribe to (empty = subscribe to all access control events)
     * @return array Response from device
     */
    public function configureWebhook(string $webhookUrl, int $hostId = 1, array $eventTypes = []): array
    {
        $urlParts = parse_url($webhookUrl);
        $protocol = strtoupper($urlParts['scheme'] ?? 'http');
        $port = $urlParts['port'] ?? ($protocol === 'HTTPS' ? 443 : 80);

        // Don't specify event types - device will send all events
        // This is simpler and avoids XML formatting issues with arrays
        return $this->configureHttpHost(
            url: $webhookUrl,
            id: $hostId,
            protocol: $protocol,
            port: $port,
            httpAuthType: 'none',
            eventTypes: []  // Empty array = subscribe to all events
        );
    }

    /**
     * Configure event listening service with IP, Port, and Path
     * This method is designed for environments where you have a specific event server IP and port
     * (Listening Service mode as described in Hikvision ISAPI documentation Section 6.1)
     *
     * The device will send events to: http(s)://<eventServerIp>:<eventServerPort><urlPath>
     *
     * @param  string  $eventServerIp  Event server IP address or domain name (e.g., '192.168.1.100' or 'api.company.com')
     * @param  int  $eventServerPort  Event server port (e.g., 8080)
     * @param  string  $urlPath  URL path on the event server (e.g., '/api/webhooks/hikvision/events')
     * @param  string  $protocol  Protocol type (HTTP or HTTPS, default HTTP)
     * @param  int  $hostId  Host ID (1-8, default 1)
     * @param  string  $httpAuthType  Authentication type (none, basic, digest, default none)
     * @param  string|null  $username  Username for authentication (if required)
     * @param  string|null  $password  Password for authentication (if required)
     * @param  array  $eventTypes  Event types to subscribe to (empty = all events)
     * @return array Response from device
     */
    public function configureEventListeningHost(
        string $eventServerIp,
        int $eventServerPort,
        string $urlPath = '/',
        string $protocol = 'HTTP',
        int $hostId = 1,
        string $httpAuthType = 'none',
        ?string $username = null,
        ?string $password = null,
        array $eventTypes = []
    ): array {
        // Validate IP address or domain name
        if (!filter_var($eventServerIp, FILTER_VALIDATE_IP) && !$this->isValidDomain($eventServerIp)) {
            throw new \InvalidArgumentException("Invalid IP address or domain name: {$eventServerIp}");
        }

        // Validate port range
        if ($eventServerPort < 1 || $eventServerPort > 65535) {
            throw new \InvalidArgumentException("Port must be between 1 and 65535, got: {$eventServerPort}");
        }

        // Ensure URL path starts with /
        if (!str_starts_with($urlPath, '/')) {
            $urlPath = '/'.$urlPath;
        }

        // Determine if input is IP address or domain name
        $isIpAddress = filter_var($eventServerIp, FILTER_VALIDATE_IP);

        $data = [
            'HttpHostNotification' => [
                'id' => $hostId,
                'url' => $urlPath,
                'protocolType' => strtoupper($protocol),
                'parameterFormatType' => 'XML',
                'addressingFormatType' => $isIpAddress ? 'ipaddress' : 'hostname',
                'portNo' => $eventServerPort,
                'httpAuthenticationMethod' => $httpAuthType,
                'enabled' => true,
            ],
        ];

        // Add IP address or hostname based on type
        if ($isIpAddress) {
            $data['HttpHostNotification']['ipAddress'] = $eventServerIp;
        } else {
            $data['HttpHostNotification']['hostName'] = $eventServerIp;
        }

        // Add authentication if provided
        if ($httpAuthType !== 'none' && $username && $password) {
            $data['HttpHostNotification']['userName'] = $username;
            $data['HttpHostNotification']['password'] = $password;
        }

        // Add event types if specified
        if (!empty($eventTypes)) {
            $data['HttpHostNotification']['eventList'] = [
                'eventType' => $eventTypes,
            ];
        }

        $endpoint = sprintf(self::ENDPOINT_HTTP_HOST, $hostId);

        return $this->client->putXml($endpoint, $data);
    }

    /**
     * Validate if the given string is a valid domain name
     *
     * @param  string  $domain  Domain name to validate
     * @return bool True if valid domain, false otherwise
     */
    private function isValidDomain(string $domain): bool
    {
        // Basic domain validation
        // Allows: example.com, sub.example.com, localhost, etc.
        $pattern = '/^(?:[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)*[a-zA-Z0-9](?:[a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?$/';

        return (bool) preg_match($pattern, $domain);
    }
}
