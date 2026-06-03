# Hikvision ISAPI Laravel Package

[![Latest Version](https://img.shields.io/badge/version-v1.5.4-brightgreen.svg)](https://github.com/shaykhnazar/hikvision-isapi/releases)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%5E8.2-blue.svg)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-11%20%7C%2012-red.svg)](https://laravel.com/)

A clean, modern Laravel package for integrating with **Hikvision ISAPI Face Recognition Terminals** and access control devices. Supports multi-device management, universal device providers (config, database, API), multi-tenant architectures, and real-time event webhooks.

## Features

- **Real-Time Event Webhooks**: Configure devices to push events to your API endpoints (v1.4.0+)
- **Multi-Device Support**: Manage unlimited Hikvision terminals simultaneously (v1.2.0+)
- **Universal Device Providers**: Load devices from config, database, API, or custom sources (v1.3.0+)
- **Multi-Tenant Ready**: Tenant-scoped device loading with runtime registration (v1.3.0+)
- **Device Management**: Get device info, status, and capabilities
- **Person Management**: Add, update, search, and delete persons
- **Card Management**: Handle access cards with full CRUD operations
- **Face Recognition**: Upload and manage face images with advanced search (v1.1.0+)
- **Fingerprint Support**: Register and manage fingerprints
- **Access Control**: Control doors remotely
- **Event Handling**: Search and subscribe to access events
- **Event Notifications**: HTTP webhooks for real-time event delivery (v1.4.0+)
- **Clean Architecture**: SOLID principles, dependency injection, contracts
- **Type Safety**: PHP 8.2+ features (enums, readonly properties)
- **Performance**: Built-in caching with configurable TTL (v1.3.0+)
- **XML/JSON Support**: Automatic format detection and conversion (v1.4.0+)
- **Laravel 11/12**: Supports Laravel 11.x and 12.x with service provider and facade

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Quick Start](#quick-start)
- [Event Webhooks & Notifications](#event-webhooks--notifications-v140)
  - [Configure Webhook Endpoint](#configure-webhook-endpoint)
  - [Simple Webhook Setup](#simple-webhook-setup)
  - [Advanced Webhook Configuration](#advanced-webhook-configuration)
  - [Testing Webhooks](#testing-webhooks)
  - [Receiving Webhook Events](#receiving-webhook-events)
- [Multi-Device Support](#multi-device-support)
  - [Configuration from Config Files](#configuration-from-config-files-default)
  - [Loading Devices from Database](#loading-devices-from-database)
  - [Using Eloquent Models with CallbackProvider](#using-eloquent-models-with-callbackprovider)
  - [Multi-Tenant Support](#multi-tenant-support-example)
  - [Runtime Device Registration](#runtime-device-registration)
  - [Device Manager Methods](#device-manager-methods)
- [Services Overview](#services-overview)
- [DTOs (Data Transfer Objects)](#dtos-data-transfer-objects)
- [Enums](#enums)
- [Error Handling](#error-handling)
- [Advanced Usage](#advanced-usage)
- [Testing](#testing)
- [Troubleshooting](#troubleshooting)
- [Security](#security)
- [Contributing](#contributing)
- [Changelog](#changelog)

## Requirements

- PHP ^8.2
- Laravel / illuminate/support ^11.0 || ^12.0
- Guzzle HTTP ^7.8
- ext-curl
- ext-json
- ext-simplexml
- ext-libxml

## Installation

Install the package via Composer:

```bash
composer require shaykhnazar/hikvision-isapi
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag=hikvision-config
```

## Configuration

Add these environment variables to your `.env` file:

```env
HIKVISION_IP=192.168.1.100
HIKVISION_PORT=80
HIKVISION_USERNAME=admin
HIKVISION_PASSWORD=your_password
HIKVISION_PROTOCOL=http
HIKVISION_TIMEOUT=30
HIKVISION_VERIFY_SSL=false
HIKVISION_FORMAT=json
```

### Configuration File

The published `config/hikvision.php` supports multiple devices:

```php
return [
    'default' => env('HIKVISION_DEFAULT_DEVICE', 'primary'),

    'devices' => [
        'primary' => [
            'ip' => env('HIKVISION_IP', '192.168.1.100'),
            'port' => env('HIKVISION_PORT', 80),
            'username' => env('HIKVISION_USERNAME', 'admin'),
            'password' => env('HIKVISION_PASSWORD'),
            'protocol' => env('HIKVISION_PROTOCOL', 'http'),
            'timeout' => env('HIKVISION_TIMEOUT', 30),
            'verify_ssl' => env('HIKVISION_VERIFY_SSL', false),
        ],
    ],

    'format' => env('HIKVISION_FORMAT', 'json'),

    'logging' => [
        'enabled' => env('HIKVISION_LOGGING', true),
        'channel' => env('HIKVISION_LOG_CHANNEL', 'stack'),
    ],
];
```

## Quick Start

### Check Device Status

```php
use Shaykhnazar\HikvisionIsapi\Services\DeviceService;

$deviceService = app(DeviceService::class);

if ($deviceService->isOnline()) {
    $info = $deviceService->getInfo();
    echo "Device Model: " . $info['DeviceInfo']['model'];
}
```

### Add a Person

```php
use Shaykhnazar\HikvisionIsapi\Services\PersonService;
use Shaykhnazar\HikvisionIsapi\DTOs\Person;
use Shaykhnazar\HikvisionIsapi\Enums\UserType;

$personService = app(PersonService::class);

$person = new Person(
    employeeNo: 'EMP001',
    name: 'John Doe',
    userType: UserType::NORMAL,
    validEnabled: true,
    beginTime: now()->toISOString(),
    endTime: now()->addYear()->toISOString(),
    doorRight: '1',
    rightPlan: [
        ['doorNo' => 1, 'planTemplateNo' => '1']
    ]
);

$response = $personService->add($person);
```

### Upload Face Image

```php
use Shaykhnazar\HikvisionIsapi\Services\FaceService;

$faceService = app(FaceService::class);

// Read image file
$imageData = file_get_contents('path/to/photo.jpg');
$imageBase64 = base64_encode($imageData);

// Upload face (person must exist first)
$response = $faceService->uploadFace('EMP001', $imageBase64, 1);
```

### Search and Manage Face Data

```php
use Shaykhnazar\HikvisionIsapi\Services\FaceService;

$faceService = app(FaceService::class);

// Search face data with pagination
$results = $faceService->searchFace(
    page: 0,
    maxResults: 30,
    faceLibType: 'blackFD',
    fdid: 1,
    fpid: '31903791410044'
);

// Upload face data record with image file
$imageContent = file_get_contents('face.jpg');
$response = $faceService->uploadFaceDataRecord(
    fdid: 1,
    fpid: '31903791410044',
    imageContent: $imageContent,
    faceLibType: 'blackFD'
);

// Delete face search data
$faceService->deleteFaceSearch(fdid: 1, faceLibType: 'blackFD');
```

### Search Persons

```php
$personService = app(PersonService::class);

// Get total count
$total = $personService->count();

// Search with pagination (30 persons per page)
$persons = $personService->search(page: 0, maxResults: 30);

foreach ($persons as $person) {
    echo "{$person->employeeNo}: {$person->name}\n";
}
```

### Add Card

```php
use Shaykhnazar\HikvisionIsapi\Services\CardService;
use Shaykhnazar\HikvisionIsapi\DTOs\Card;

$cardService = app(CardService::class);

$card = new Card(
    employeeNo: 'EMP001',
    cardNo: '1234567890',
    cardType: 'normal'
);

$response = $cardService->add($card);
```

### Control Doors

```php
use Shaykhnazar\HikvisionIsapi\Services\AccessControlService;

$accessControl = app(AccessControlService::class);

// Open door
$accessControl->openDoor(1);

// Get door status
$status = $accessControl->getDoorStatus(1);

// Close door
$accessControl->closeDoor(1);
```

### Search Events

```php
use Shaykhnazar\HikvisionIsapi\Services\EventService;

$eventService = app(EventService::class);

$events = $eventService->search([
    'major' => 5,  // Access control
    'minor' => 75, // Face recognition
    'startTime' => now()->subDay()->toISOString(),
    'endTime' => now()->toISOString(),
], page: 0, maxResults: 50);
```

## Event Webhooks & Notifications (v1.4.0+)

Configure Hikvision devices to automatically push events (access granted, face recognized, etc.) to your application's webhook endpoint in real-time. This eliminates the need for polling and provides instant event notifications.

### Configure Webhook Endpoint

The `EventNotificationService` allows you to configure HTTP event notifications on Hikvision devices:

```php
use Shaykhnazar\HikvisionIsapi\Services\EventNotificationService;

$eventNotificationService = app(EventNotificationService::class);
```

### Simple Webhook Setup

The easiest way to set up a webhook is using the `configureWebhook()` method:

```php
// Configure device to send all events to your webhook endpoint
$webhookUrl = 'https://your-api.com/api/webhooks/hikvision/events';

$response = $eventNotificationService->configureWebhook(
    webhookUrl: $webhookUrl,
    hostId: 1 // Host ID (1-8), default is 1
);

// Enable the webhook
$eventNotificationService->enableHttpHost(1);
```

### Advanced Webhook Configuration

For more control over webhook configuration, use `configureHttpHost()`:

```php
$response = $eventNotificationService->configureHttpHost(
    url: 'https://your-api.com/api/webhooks/hikvision/events',
    id: 1,                      // Host ID (1-8)
    protocol: 'HTTPS',          // HTTP or HTTPS
    port: 443,                  // Port number
    httpAuthType: 'basic',      // none, basic, or digest
    username: 'webhook_user',   // Optional: for authentication
    password: 'webhook_pass',   // Optional: for authentication
    eventTypes: [               // Optional: specific event types (empty = all events)
        'AccessControllerEvent',
        'VideoMotion',
        'linedetection'
    ]
);
```

### Managing Webhook Hosts

```php
// Get webhook configuration
$config = $eventNotificationService->getHttpHost(1);

// Get all webhook configurations (supports up to 8 hosts)
$allConfigs = $eventNotificationService->getAllHttpHosts();

// Enable webhook
$eventNotificationService->enableHttpHost(1);

// Disable webhook
$eventNotificationService->disableHttpHost(1);

// Remove webhook configuration
$eventNotificationService->removeHttpHost(1);

// Get notification capabilities
$capabilities = $eventNotificationService->getCapabilities();
```

### Testing Webhooks

Test if your webhook endpoint is reachable from the device:

```php
// Send a test event to configured webhook
$response = $eventNotificationService->testHttpNotification(1);
```

### Receiving Webhook Events

Create a controller to receive webhook events from Hikvision devices:

```php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class HikvisionWebhookController extends Controller
{
    /**
     * Receive events from Hikvision devices
     */
    public function handleEvent(Request $request)
    {
        // Hikvision sends events as XML
        $xmlData = $request->getContent();

        // Parse XML to array
        $xml = simplexml_load_string($xmlData);
        $event = json_decode(json_encode($xml), true);

        // Log the event
        Log::info('Hikvision Event Received', $event);

        // Process event based on type
        $eventType = $event['eventType'] ?? null;

        match($eventType) {
            'AccessControllerEvent' => $this->handleAccessControl($event),
            'faceDetection' => $this->handleFaceDetection($event),
            'VideoMotion' => $this->handleMotionDetection($event),
            default => Log::warning('Unknown event type', ['type' => $eventType])
        };

        // Return 200 OK to acknowledge receipt
        return response('OK', 200);
    }

    protected function handleAccessControl(array $event)
    {
        // Example: Handle access control event
        $employeeNo = $event['employeeNoString'] ?? null;
        $cardNo = $event['cardNo'] ?? null;
        $doorNo = $event['doorNo'] ?? null;

        Log::info('Access Control Event', [
            'employee' => $employeeNo,
            'card' => $cardNo,
            'door' => $doorNo,
            'time' => $event['dateTime'] ?? now()
        ]);

        // Your business logic here
        // - Record attendance
        // - Send notifications
        // - Update access logs
    }

    protected function handleFaceDetection(array $event)
    {
        // Handle face detection event
        Log::info('Face Detection Event', $event);
    }

    protected function handleMotionDetection(array $event)
    {
        // Handle motion detection event
        Log::info('Motion Detection Event', $event);
    }
}
```

**Route setup:**

```php
// routes/api.php
Route::post('webhooks/hikvision/events', [HikvisionWebhookController::class, 'handleEvent'])
    ->name('hikvision.webhook');
```

### Multi-Device Webhook Setup

Configure webhooks for multiple devices:

```php
use Shaykhnazar\HikvisionIsapi\Facades\Hikvision;
use Shaykhnazar\HikvisionIsapi\Services\EventNotificationService;

$devices = ['entrance', 'exit', 'canteen'];
$webhookUrl = 'https://your-api.com/api/webhooks/hikvision/events';

foreach ($devices as $deviceName) {
    $client = Hikvision::device($deviceName);
    $eventService = new EventNotificationService($client);

    // Configure webhook with device name in URL for identification
    $deviceWebhookUrl = $webhookUrl . '?device=' . $deviceName;

    $eventService->configureWebhook($deviceWebhookUrl);
    $eventService->enableHttpHost(1);

    Log::info("Webhook configured for device: {$deviceName}");
}
```

### Webhook Security

Protect your webhook endpoint:

```php
// Add authentication to webhook configuration
$eventNotificationService->configureHttpHost(
    url: 'https://your-api.com/api/webhooks/hikvision/events',
    id: 1,
    protocol: 'HTTPS',
    port: 443,
    httpAuthType: 'basic',
    username: env('HIKVISION_WEBHOOK_USER'),
    password: env('HIKVISION_WEBHOOK_PASSWORD')
);

// In your controller, validate the credentials
public function handleEvent(Request $request)
{
    // Laravel automatically handles basic auth with middleware
    // Or manually validate:
    if ($request->getUser() !== env('HIKVISION_WEBHOOK_USER') ||
        $request->getPassword() !== env('HIKVISION_WEBHOOK_PASSWORD')) {
        return response('Unauthorized', 401);
    }

    // Process event...
}
```

### Webhook Event Examples

**Access Control Event (Face Recognition):**

```xml
<EventNotificationAlert>
    <eventType>AccessControllerEvent</eventType>
    <eventState>active</eventState>
    <eventDescription>Access Control Event</eventDescription>
    <dateTime>2025-10-30T14:30:00Z</dateTime>
    <ActivePost>
        <eventType>AccessControllerEvent</eventType>
        <employeeNoString>EMP001</employeeNoString>
        <name>John Doe</name>
        <cardNo>1234567890</cardNo>
        <doorNo>1</doorNo>
        <swipeResult>success</swipeResult>
    </ActivePost>
</EventNotificationAlert>
```

**Motion Detection Event:**

```xml
<EventNotificationAlert>
    <eventType>VideoMotion</eventType>
    <eventState>active</eventState>
    <dateTime>2025-10-30T14:35:00Z</dateTime>
</EventNotificationAlert>
```

## Using the Facade

```php
use Shaykhnazar\HikvisionIsapi\Facades\HikvisionIsapi;

// Get device info
$info = HikvisionIsapi::get('/ISAPI/System/deviceInfo');

// Custom endpoint
$response = HikvisionIsapi::post('/ISAPI/CustomEndpoint', [
    'key' => 'value'
]);
```

## Services Overview

### DeviceService

```php
$deviceService = app(DeviceService::class);

$deviceService->getInfo();           // Get device information
$deviceService->getCapabilities();   // Get device capabilities
$deviceService->getStatus();         // Get device status
$deviceService->isOnline();          // Check if device is online
```

### PersonService

```php
$personService = app(PersonService::class);

$personService->add(Person $person);              // Add person
$personService->update(Person $person);           // Update person
$personService->apply(Person $person);            // Apply person changes
$personService->search(int $page, int $maxResults); // Search persons
$personService->delete(array $employeeNos);       // Delete persons
$personService->count();                          // Count persons
$personService->getCapabilities();                // Get capabilities
```

### CardService

```php
$cardService = app(CardService::class);

$cardService->add(Card $card);                    // Add card
$cardService->update(Card $card);                 // Update card
$cardService->search(...);                        // Search cards
$cardService->delete(array $employeeNos);         // Delete cards
$cardService->deleteAll();                        // Delete all cards
$cardService->count(?string $employeeNo);         // Count cards
$cardService->batchAdd(array $cards);             // Batch add cards
```

### FaceService

```php
$faceService = app(FaceService::class);

$faceService->uploadFace(string $employeeNo, string $imageBase64, int $fdid);
$faceService->deleteFace(int $fdid, int $fpid);
$faceService->searchFace(int $page, int $maxResults, string $faceLibType, ?int $fdid, ?string $fpid);
$faceService->deleteFaceSearch(int $fdid, string $faceLibType);
$faceService->uploadFaceDataRecord(int $fdid, string $fpid, string $imageContent, string $faceLibType);
$faceService->getLibraries();
$faceService->createLibrary(array $data);
$faceService->getCapabilities();
```

### FingerprintService

```php
$fingerprintService = app(FingerprintService::class);

$fingerprintService->add(string $employeeNo, int $fingerprintId, string $data);
$fingerprintService->search(...);
$fingerprintService->capture(int $timeout);
$fingerprintService->delete(array $employeeNos);
$fingerprintService->getCapabilities();
```

### AccessControlService

```php
$accessControl = app(AccessControlService::class);

$accessControl->openDoor(int $doorNo);
$accessControl->closeDoor(int $doorNo);
$accessControl->getDoorStatus(int $doorNo);
```

### EventService

```php
$eventService = app(EventService::class);

$eventService->search(array $conditions, int $page, int $maxResults);
$eventService->count(array $conditions);
$eventService->subscribe(array $eventTypes, int $heartbeat);
```

### EventNotificationService

```php
$eventNotificationService = app(EventNotificationService::class);

// Simple webhook setup
$eventNotificationService->configureWebhook(string $webhookUrl, int $hostId);

// Advanced configuration
$eventNotificationService->configureHttpHost(string $url, int $id, string $protocol, int $port, string $httpAuthType, ?string $username, ?string $password, array $eventTypes);

// Manage webhooks
$eventNotificationService->getHttpHost(int $id);
$eventNotificationService->getAllHttpHosts();
$eventNotificationService->enableHttpHost(int $id);
$eventNotificationService->disableHttpHost(int $id);
$eventNotificationService->removeHttpHost(int $id);

// Testing
$eventNotificationService->testHttpNotification(int $id);

// Capabilities
$eventNotificationService->getCapabilities();
```

## DTOs (Data Transfer Objects)

### Person DTO

```php
use Shaykhnazar\HikvisionIsapi\DTOs\Person;
use Shaykhnazar\HikvisionIsapi\Enums\UserType;

$person = new Person(
    employeeNo: 'EMP001',
    name: 'John Doe',
    userType: UserType::NORMAL,
    validEnabled: true,
    beginTime: '2025-01-01T00:00:00',
    endTime: '2025-12-31T23:59:59',
    doorRight: '1',
    rightPlan: [
        ['doorNo' => 1, 'planTemplateNo' => '1']
    ],
    email: 'john@example.com',
    phoneNumber: '+1234567890',
    organizationId: 1,
    belongGroup: null
);

// Convert to array for API
$array = $person->toArray();

// Create from API response
$person = Person::fromArray($response);
```

### Card DTO

```php
use Shaykhnazar\HikvisionIsapi\DTOs\Card;

$card = new Card(
    employeeNo: 'EMP001',
    cardNo: '1234567890',
    cardType: 'normal',
    enabled: true
);
```

### Face DTO

```php
use Shaykhnazar\HikvisionIsapi\DTOs\Face;

$face = new Face(
    employeeNo: 'EMP001',
    faceData: base64_encode($imageData),
    faceLibId: 1,
    faceLibType: 'blackFD'
);
```

## Enums

### UserType

```php
use Shaykhnazar\HikvisionIsapi\Enums\UserType;

UserType::NORMAL    // Normal user
UserType::VISITOR   // Visitor
UserType::BLOCKLIST // Blocklist user

// Get label
$label = UserType::NORMAL->label(); // "Normal User"
```

### EventType

```php
use Shaykhnazar\HikvisionIsapi\Enums\EventType;

EventType::ACCESS_GRANTED  // 0x01
EventType::ACCESS_DENIED   // 0x02
EventType::FACE_RECOGNIZED // 0x4b
EventType::CARD_SWIPED     // 0x05

// Get description
$desc = EventType::ACCESS_GRANTED->description(); // "Access Granted"
```

## Error Handling

All exceptions extend `HikvisionException`:

```php
use Shaykhnazar\HikvisionIsapi\Exceptions\HikvisionException;
use Shaykhnazar\HikvisionIsapi\Exceptions\AuthenticationException;
use Shaykhnazar\HikvisionIsapi\Exceptions\DeviceNotFoundException;

try {
    $response = $personService->add($person);
} catch (AuthenticationException $e) {
    // Handle authentication errors
    Log::error('Authentication failed', ['error' => $e->getMessage()]);
} catch (DeviceNotFoundException $e) {
    // Handle device not found
    Log::error('Device not found', ['error' => $e->getMessage()]);
} catch (HikvisionException $e) {
    // Handle other Hikvision errors
    Log::error('Hikvision error', ['error' => $e->getMessage()]);
}
```

## Multi-Device Support

The package supports managing multiple Hikvision devices simultaneously. This is useful when you have multiple terminals at different locations (entrance, exit, canteen, etc.).

**Device Sources Supported:**
- ✅ Config files (default)
- ✅ Database tables
- ✅ Custom callbacks (API, Redis, cache, etc.)
- ✅ Runtime registration

### Configuration from Config Files (Default)

Configure multiple devices in `config/hikvision.php`:

```php
return [
    'default' => env('HIKVISION_DEFAULT_DEVICE', 'primary'),

    'devices' => [
        'primary' => [
            'ip' => env('HIKVISION_IP', '192.168.1.100'),
            'port' => env('HIKVISION_PORT', 80),
            'username' => env('HIKVISION_USERNAME', 'admin'),
            'password' => env('HIKVISION_PASSWORD'),
            'protocol' => env('HIKVISION_PROTOCOL', 'http'),
            'timeout' => env('HIKVISION_TIMEOUT', 30),
            'verify_ssl' => env('HIKVISION_VERIFY_SSL', false),
        ],

        'entrance' => [
            'ip' => env('HIKVISION_ENTRANCE_IP', '192.168.1.101'),
            'port' => env('HIKVISION_ENTRANCE_PORT', 80),
            'username' => env('HIKVISION_ENTRANCE_USERNAME', 'admin'),
            'password' => env('HIKVISION_ENTRANCE_PASSWORD'),
            'protocol' => env('HIKVISION_ENTRANCE_PROTOCOL', 'http'),
            'timeout' => env('HIKVISION_ENTRANCE_TIMEOUT', 30),
            'verify_ssl' => env('HIKVISION_ENTRANCE_VERIFY_SSL', false),
        ],

        'exit' => [
            'ip' => env('HIKVISION_EXIT_IP', '192.168.1.102'),
            'port' => env('HIKVISION_EXIT_PORT', 80),
            'username' => env('HIKVISION_EXIT_USERNAME', 'admin'),
            'password' => env('HIKVISION_EXIT_PASSWORD'),
            'protocol' => env('HIKVISION_EXIT_PROTOCOL', 'http'),
            'timeout' => env('HIKVISION_EXIT_TIMEOUT', 30),
            'verify_ssl' => env('HIKVISION_EXIT_VERIFY_SSL', false),
        ],

        'canteen' => [
            'ip' => env('HIKVISION_CANTEEN_IP', '192.168.1.103'),
            'port' => env('HIKVISION_CANTEEN_PORT', 80),
            'username' => env('HIKVISION_CANTEEN_USERNAME', 'admin'),
            'password' => env('HIKVISION_CANTEEN_PASSWORD'),
            'protocol' => env('HIKVISION_CANTEEN_PROTOCOL', 'http'),
            'timeout' => env('HIKVISION_CANTEEN_TIMEOUT', 30),
            'verify_ssl' => env('HIKVISION_CANTEEN_VERIFY_SSL', false),
        ],
    ],
];
```

### Environment Variables

Add device-specific environment variables to `.env`:

```env
# Primary Device (Default)
HIKVISION_DEFAULT_DEVICE=primary
HIKVISION_IP=192.168.1.100
HIKVISION_PORT=80
HIKVISION_USERNAME=admin
HIKVISION_PASSWORD=your_password

# Entrance Device
HIKVISION_ENTRANCE_IP=192.168.1.101
HIKVISION_ENTRANCE_PORT=80
HIKVISION_ENTRANCE_USERNAME=admin
HIKVISION_ENTRANCE_PASSWORD=entrance_password

# Exit Device
HIKVISION_EXIT_IP=192.168.1.102
HIKVISION_EXIT_PORT=80
HIKVISION_EXIT_USERNAME=admin
HIKVISION_EXIT_PASSWORD=exit_password

# Canteen Device
HIKVISION_CANTEEN_IP=192.168.1.103
HIKVISION_CANTEEN_PORT=80
HIKVISION_CANTEEN_USERNAME=admin
HIKVISION_CANTEEN_PASSWORD=canteen_password
```

### Using Multiple Devices with Facade

```php
use Shaykhnazar\HikvisionIsapi\Facades\Hikvision;

// Use default device
$defaultClient = Hikvision::default();
$info = $defaultClient->get('/ISAPI/System/deviceInfo');

// Use specific devices
$entranceClient = Hikvision::device('entrance');
$exitClient = Hikvision::device('exit');
$canteenClient = Hikvision::device('canteen');

// Get device information from each terminal
$entranceInfo = $entranceClient->get('/ISAPI/System/deviceInfo');
$exitInfo = $exitClient->get('/ISAPI/System/deviceInfo');
$canteenInfo = $canteenClient->get('/ISAPI/System/deviceInfo');

// List all available devices
$devices = Hikvision::availableDevices();
// Returns: ['primary', 'entrance', 'exit', 'canteen']

// Check if device exists
if (Hikvision::hasDevice('entrance')) {
    // Work with entrance device
}
```

### Using Multiple Devices with Services

```php
use Shaykhnazar\HikvisionIsapi\Facades\Hikvision;
use Shaykhnazar\HikvisionIsapi\Services\PersonService;
use Shaykhnazar\HikvisionIsapi\DTOs\Person;
use Shaykhnazar\HikvisionIsapi\Enums\UserType;

// Create person DTO
$person = new Person(
    employeeNo: 'EMP001',
    name: 'John Doe',
    userType: UserType::NORMAL,
    validEnabled: true,
    beginTime: now()->toISOString(),
    endTime: now()->addYear()->toISOString()
);

// Get device-specific clients
$entranceClient = Hikvision::device('entrance');
$exitClient = Hikvision::device('exit');
$canteenClient = Hikvision::device('canteen');

// Create person service for each device
$entrancePersonService = new PersonService($entranceClient);
$exitPersonService = new PersonService($exitClient);
$canteenPersonService = new PersonService($canteenClient);

// Add person to all devices
$entrancePersonService->add($person);
$exitPersonService->add($person);
$canteenPersonService->add($person);
```

### Syncing Employee to Multiple Devices

```php
use Shaykhnazar\HikvisionIsapi\Facades\Hikvision;
use Shaykhnazar\HikvisionIsapi\Services\PersonService;
use Shaykhnazar\HikvisionIsapi\Services\FaceService;
use Shaykhnazar\HikvisionIsapi\DTOs\Person;

class EmployeeSyncService
{
    public function syncToAllDevices(Person $person, string $faceImagePath): array
    {
        $results = [];
        $devices = Hikvision::availableDevices();

        foreach ($devices as $deviceName) {
            try {
                // Get device client
                $client = Hikvision::device($deviceName);

                // Create services for this device
                $personService = new PersonService($client);
                $faceService = new FaceService($client);

                // Add person
                $personService->add($person);

                // Upload face
                $imageData = file_get_contents($faceImagePath);
                $imageBase64 = base64_encode($imageData);
                $faceService->uploadFace($person->employeeNo, $imageBase64, 1);

                $results[$deviceName] = [
                    'success' => true,
                    'message' => 'Synced successfully',
                ];
            } catch (\Exception $e) {
                $results[$deviceName] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
```

### Device Manager Methods

The `DeviceManager` provides the following methods:

```php
use Shaykhnazar\HikvisionIsapi\Facades\Hikvision;

// Get client for specific device (or default if null)
$client = Hikvision::device('entrance');
$client = Hikvision::device(); // Uses default device

// Get default device client
$defaultClient = Hikvision::default();

// Get all available device names
$devices = Hikvision::availableDevices();
// Returns: ['primary', 'entrance', 'exit', 'canteen']

// Check if device exists in configuration
$exists = Hikvision::hasDevice('entrance'); // true or false

// Reload devices from provider (useful for database-driven configs)
Hikvision::reload(); // Clears cache and reloads from source

// Clear cached clients (useful for testing)
Hikvision::clearClients();

// Register device at runtime (v1.3.0+)
Hikvision::registerDevice('temp_device', [
    'ip' => '192.168.1.150',
    'port' => 80,
    'username' => 'admin',
    'password' => 'password',
    'protocol' => 'http',
    'timeout' => 30,
    'verify_ssl' => false,
]);

// Switch device provider at runtime (v1.3.0+)
use Shaykhnazar\HikvisionIsapi\Client\Providers\DatabaseDeviceProvider;
$dbProvider = new DatabaseDeviceProvider(table: 'terminals');
Hikvision::setProvider($dbProvider);
```

### Backward Compatibility

The package maintains **100% backward compatibility**. If you're using the default device setup, your existing code will work without any changes:

```php
use Shaykhnazar\HikvisionIsapi\Services\PersonService;

// This still works - uses default device
$personService = app(PersonService::class);
$person = $personService->add($newPerson);
```

### Loading Devices from Database

For applications that store terminal configurations in database (multi-tenant, dynamic terminals, etc.), use the `DatabaseDeviceProvider`:

#### Step 1: Create Terminals Table

```php
// Migration: create_terminals_table.php
Schema::create('terminals', function (Blueprint $table) {
    $table->id();
    $table->string('name')->unique(); // Device identifier
    $table->string('ip');
    $table->integer('port')->default(80);
    $table->string('username');
    $table->string('password');
    $table->string('protocol')->default('http');
    $table->integer('timeout')->default(30);
    $table->boolean('verify_ssl')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

#### Step 2: Register Database Provider

In your `AppServiceProvider` or custom service provider:

```php
use Shaykhnazar\HikvisionIsapi\Client\Providers\DatabaseDeviceProvider;

public function register(): void
{
    // Bind custom device provider
    $this->app->singleton('hikvision.device.provider', function ($app) {
        return new DatabaseDeviceProvider(
            table: 'terminals',
            nameColumn: 'name',
            configColumns: [
                'ip' => 'ip',
                'port' => 'port',
                'username' => 'username',
                'password' => 'password',
                'protocol' => 'protocol',
                'timeout' => 'timeout',
                'verify_ssl' => 'verify_ssl',
            ],
            defaultDevice: 'primary',
            whereConditions: ['is_active' => true], // Only load active terminals
            cache: true, // Enable caching
            cacheTtl: 3600 // Cache for 1 hour
        );
    });
}
```

#### Step 3: Use Terminals from Database

```php
use Shaykhnazar\HikvisionIsapi\Facades\Hikvision;
use Shaykhnazar\HikvisionIsapi\Services\PersonService;

// Get all available terminals from database
$terminals = Hikvision::availableDevices();
// Returns: ['entrance', 'exit', 'canteen', 'office'] - loaded from DB

// Use specific terminal
$entranceClient = Hikvision::device('entrance');
$personService = new PersonService($entranceClient);

// Add person to entrance terminal
$personService->add($person);
```

#### Step 4: Reload Devices When Database Changes

```php
use Shaykhnazar\HikvisionIsapi\Facades\Hikvision;

// After adding/updating terminals in database
Hikvision::reload(); // Clears cache and reloads from DB
```

### Using Eloquent Models with CallbackProvider

For more complex scenarios with Eloquent relationships:

```php
use Shaykhnazar\HikvisionIsapi\Client\Providers\CallbackDeviceProvider;
use App\Models\Terminal;

// In your service provider
$this->app->singleton('hikvision.device.provider', function ($app) {
    return CallbackDeviceProvider::fromEloquent(
        query: Terminal::where('status', 'active')
                      ->where('company_id', auth()->user()->company_id),
        nameColumn: 'slug',
        configMap: [
            'ip' => 'ip_address',
            'port' => 'port',
            'username' => 'username',
            'password' => 'password',
            'protocol' => 'protocol',
            'timeout' => 'connection_timeout',
            'verify_ssl' => 'ssl_enabled',
        ]
    );
});
```

### Multi-Tenant Support Example

For multi-tenant applications where each tenant has their own terminals:

```php
use Shaykhnazar\HikvisionIsapi\Client\Providers\CallbackDeviceProvider;
use App\Models\Terminal;

// In AppServiceProvider
$this->app->singleton('hikvision.device.provider', function ($app) {
    return new CallbackDeviceProvider(
        deviceNamesCallback: function () {
            // Only load terminals for current tenant
            $tenantId = auth()->user()->tenant_id;
            return Terminal::where('tenant_id', $tenantId)
                          ->where('is_active', true)
                          ->pluck('name')
                          ->toArray();
        },
        deviceConfigCallback: function (string $deviceName) {
            $tenantId = auth()->user()->tenant_id;
            $terminal = Terminal::where('tenant_id', $tenantId)
                               ->where('name', $deviceName)
                               ->first();

            if (!$terminal) {
                return null;
            }

            return [
                'ip' => $terminal->ip,
                'port' => $terminal->port,
                'username' => $terminal->username,
                'password' => decrypt($terminal->password), // Decrypt if encrypted
                'protocol' => $terminal->protocol,
                'timeout' => $terminal->timeout,
                'verify_ssl' => $terminal->verify_ssl,
            ];
        },
        defaultDevice: 'primary'
    );
});
```

### Runtime Device Registration

Register devices dynamically at runtime:

```php
use Shaykhnazar\HikvisionIsapi\Facades\Hikvision;

// Register a temporary device
Hikvision::registerDevice('temp_device', [
    'ip' => '192.168.1.150',
    'port' => 80,
    'username' => 'admin',
    'password' => 'password',
    'protocol' => 'http',
    'timeout' => 30,
    'verify_ssl' => false,
]);

// Use the temporary device
$client = Hikvision::device('temp_device');
```

### Switching Providers at Runtime

Change device provider dynamically:

```php
use Shaykhnazar\HikvisionIsapi\Facades\Hikvision;
use Shaykhnazar\HikvisionIsapi\Client\Providers\DatabaseDeviceProvider;
use Shaykhnazar\HikvisionIsapi\Client\Providers\ConfigDeviceProvider;

// Switch to database provider
$dbProvider = new DatabaseDeviceProvider(table: 'terminals');
Hikvision::setProvider($dbProvider);

// Now all devices are loaded from database
$devices = Hikvision::availableDevices();

// Switch back to config provider
$configProvider = new ConfigDeviceProvider(config('hikvision'));
Hikvision::setProvider($configProvider);
```

## Advanced Usage

### Batch Operations

```php
$cardService = app(CardService::class);

$cards = [
    new Card('EMP001', '1234567890'),
    new Card('EMP002', '0987654321'),
    new Card('EMP003', '1122334455'),
];

$results = $cardService->batchAdd($cards);

/*
Returns:
[
    'total' => 3,
    'success' => 3,
    'failed' => 0,
    'errors' => []
]
*/
```

### Pagination

```php
$personService = app(PersonService::class);
$total = $personService->count();
$perPage = 30;
$pages = ceil($total / $perPage);

for ($page = 0; $page < $pages; $page++) {
    $persons = $personService->search($page, $perPage);
    // Process persons...
}
```

### Custom HTTP Requests

```php
use Shaykhnazar\HikvisionIsapi\Facades\HikvisionIsapi;

// GET request
$response = HikvisionIsapi::get('/ISAPI/CustomEndpoint', [
    'param1' => 'value1',
]);

// POST request
$response = HikvisionIsapi::post('/ISAPI/CustomEndpoint', [
    'key' => 'value',
]);

// PUT request
$response = HikvisionIsapi::put('/ISAPI/CustomEndpoint', [
    'key' => 'value',
]);

// DELETE request
$response = HikvisionIsapi::delete('/ISAPI/CustomEndpoint');
```

## Testing

```bash
# Run tests
./vendor/bin/phpunit

# Run specific test file
./vendor/bin/phpunit tests/Feature/PersonServiceTest.php

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## Troubleshooting

### Authentication Failed (401)

Check your credentials in `.env`:
```bash
# Test with curl
curl -v --digest -u admin:password http://192.168.1.100/ISAPI/System/deviceInfo
```

### Connection Timeout

- Verify device IP and network connectivity
- Increase timeout in config: `HIKVISION_TIMEOUT=60`
- Check firewall rules

### Face Upload Fails

- Ensure image is JPEG format
- Check image size (max 200KB)
- Verify person exists on device first
- Image quality should be good with clear face

### Device Not Found

- Check IP address in `.env`
- Verify device is powered on
- Ensure device is on same network

## Security

See [SECURITY.md](SECURITY.md) for supported versions, vulnerability reporting instructions, and disclosure policy. Do not open public GitHub issues for suspected vulnerabilities; report them privately through the channels described there.

- Store Hikvision device and webhook credentials in environment variables or a secret manager; never commit them
- Prefer HTTPS/TLS for device and webhook communication where supported
- Enable SSL certificate verification with valid certificates in production
- Restrict device access with firewalls, VPNs, or private networks
- Implement rate limiting on your API endpoints
- Validate webhook payloads and all user-controlled input
- Treat XML input as untrusted and parse it defensively
- Avoid logging credentials, tokens, face images, biometric data, or raw personally identifiable data
- Rotate device and webhook credentials after suspected compromise

## Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch
3. Write tests for new features
4. Follow PSR-12 coding standards
5. Submit a pull request

## Changelog

For full release history, see [CHANGELOG.md](CHANGELOG.md) and the GitHub releases/tags.

### v1.5.4 (2025-11-05)

- Current stable release on Packagist.
- Fixed FaceService image filenames so supported face upload/update flows use unique `{fpid}.jpg` filenames.

### v1.4.0 (2025-10-30)

- **EventNotificationService**: New service for configuring HTTP event notifications (webhooks)
- **Real-Time Webhooks**: Configure devices to push events to your API endpoints automatically
- **XML Support**: Added `putXml()` method to HikvisionClient for XML-only endpoints
- **HTTP Client Enhancements**:
  - Added `arrayToXml()` method for converting arrays to XML format
  - Added `xmlToArray()` method for parsing XML responses
  - Automatic XML/JSON format detection based on Content-Type
- **Event Notification Methods**:
  - `configureWebhook()` - Simple one-line webhook setup
  - `configureHttpHost()` - Advanced webhook configuration with authentication
  - `enableHttpHost()` / `disableHttpHost()` - Enable/disable webhooks
  - `testHttpNotification()` - Test webhook connectivity
  - `getCapabilities()` - Get notification capabilities
- **Dependencies**: Added `ext-simplexml` and `ext-libxml` for XML processing
- **Multi-Device Webhooks**: Configure webhooks for multiple devices simultaneously
- **Webhook Security**: Support for HTTP Basic and Digest authentication
- 100% backward compatible with v1.3.0

### v1.3.0 (2025-10-13)

- **Universal Device Providers**: Framework for loading devices from any source
- **DatabaseDeviceProvider**: Load terminals from database tables with built-in caching
- **CallbackDeviceProvider**: Maximum flexibility with custom callbacks (API, Redis, etc.)
- **Multi-Tenant Support**: Tenant-scoped device loading and isolation
- **Runtime Device Registration**: Register devices dynamically at runtime
- **Provider Switching**: Change device providers on-the-fly
- **Hot Reload**: Update database-driven configurations without restart
- **Configurable Caching**: Built-in cache support with TTL control
- 100% backward compatible with v1.2.0

### v1.2.0 (2025-10-13)

- **Multi-Device Support**: Manage unlimited Hikvision devices simultaneously
- **DeviceManager**: Central manager with lazy initialization and caching
- **New Hikvision Facade**: Multi-device access with `Hikvision::device()`
- **Device Discovery**: List and validate configured devices
- **Device-Specific Clients**: Automatic client caching per device
- 100% backward compatible with single-device setups

### v1.1.0 (2025-10-13)

- **Face Search Functionality**: Search face data with pagination support
- **Face Data Record Upload**: Multipart/form-data support for face images
- **Fixed PersonService Delete Endpoint**: Updated to match ISAPI specification
- **Configuration Validation**: Required username/password validation
- **Extended HTTP Client**: Added multipart form-data upload support
- Enhanced error handling and validation

### v1.0.0 (2025-10-09)

- Initial release
- Full ISAPI support
- Person, Card, Face, Fingerprint management
- Access control and event handling
- Laravel 11/12 support
- PHP 8.2+ with modern features

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).

## Credits

- **Author**: Shaykhnazar
- **Email**: shaykhnazar@gmail.com

## Support

For issues, questions, or contributions:
- GitHub Issues: https://github.com/shaykhnazar/hikvision-isapi/issues
- Email: shaykhnazar@gmail.com

## Disclaimer

This package is designed for **legitimate access control systems** and **defensive security** purposes only. Do not use for malicious purposes.

---

**Made with ❤️ for the Laravel community**
