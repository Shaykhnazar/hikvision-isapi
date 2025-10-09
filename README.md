# Hikvision ISAPI Laravel Package

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP Version](https://img.shields.io/badge/PHP-%5E8.2-blue.svg)](https://www.php.net/)
[![Laravel Version](https://img.shields.io/badge/Laravel-%5E12.0-red.svg)](https://laravel.com/)

A clean, modern Laravel package for integrating with **Hikvision ISAPI Face Recognition Terminals** and access control devices.

## Features

- **Device Management**: Get device info, status, and capabilities
- **Person Management**: Add, update, search, and delete persons
- **Card Management**: Handle access cards with full CRUD operations
- **Face Recognition**: Upload and manage face images
- **Fingerprint Support**: Register and manage fingerprints
- **Access Control**: Control doors remotely
- **Event Handling**: Search and subscribe to access events
- **Clean Architecture**: SOLID principles, dependency injection, contracts
- **Type Safety**: PHP 8.2+ features (enums, readonly properties)
- **Laravel 12**: Full Laravel 12.x support with service provider and facade

## Requirements

- PHP ^8.2
- Laravel ^12.0
- Guzzle HTTP ^7.8
- ext-curl
- ext-json

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
$personService->deleteAll();                      // Delete all persons
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

- Store credentials in `.env` file (never commit)
- Use HTTPS in production
- Enable SSL verification with valid certificates
- Implement rate limiting on your API endpoints
- Validate all user input

## Contributing

Contributions are welcome! Please follow these guidelines:

1. Fork the repository
2. Create a feature branch
3. Write tests for new features
4. Follow PSR-12 coding standards
5. Submit a pull request

## Changelog

### v1.0.0 (2025-10-09)

- Initial release
- Full ISAPI support
- Person, Card, Face, Fingerprint management
- Access control and event handling
- Laravel 12.x support
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
