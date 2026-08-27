# Changelog

All notable changes to `hikvision-isapi` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Removed (BREAKING)
- **Dropped Laravel 11 support.** `illuminate/support` now requires `^12.0|^13.0` and `orchestra/testbench` requires `^10.0|^11.0`. Every `laravel/framework` 11.x release carries unresolved security advisories, and Composer 2.9+ refuses to resolve advisory-affected packages by default, so Laravel 11 can no longer be installed on a current toolchain and could not be tested. Projects still on Laravel 11 should stay on the 1.x line of this package.
- Unused private constants `EventService::ENDPOINT_ALERT_STREAM` and `FingerprintService::ENDPOINT_MODIFY`. Both private, so there is no public API change.

### Added
- **`bin/hikvision-probe`**: a read-only diagnostic that asks a terminal what it actually does, and writes the answers to JSON. Terminals differ on capacity limits, which endpoints exist, and the exact `subStatusCode` behind each refusal, and the documentation does not say — so this package has deliberately left those questions open rather than guess. This is how they get answered on a given model. It also settles, per device, whether a search session really does page (the `searchID` fix above) and which key an event total arrives under. Two properties are enforced by tests: **every request is a read**, including the ones that provoke refusals, so it is safe against a terminal a business depends on; and **no employee data reaches the report** — the person list is read for its shape, field names are kept and field values are not. The report also lists the questions it could not answer, because a file with ten answers and no mention of the eleventh reads as complete. The logic lives in `Probe\DeviceProbe` and is unit-tested; the binary is the argument and file handling around it. The device password is read from `HIKVISION_PASSWORD` or a prompt, never from an argument, since arguments are visible in `ps` and land in shell history.
- Laravel 13 / `illuminate/support` ^13.0 compatibility.
- Orchestra Testbench ^11.0 support for Laravel 13 package testing.
- GitHub Actions CI: a PHP 8.2/8.3/8.4 x Laravel 12/13 test matrix, plus a job for code style, static analysis and dependency auditing.
- Laravel Pint configuration (`pint.json`) and PHPStan/Larastan level 5 configuration (`phpstan.neon`), both passing with no baseline.
- `HttpClient` now accepts an optional pre-configured Guzzle `Client`, so custom middleware, retry policies or a test handler can be injected. The default behaviour is unchanged.
- Composer scripts: `composer lint`, `composer fix`, `composer analyse`, `composer test` and `composer ci`.
- Tests for `HttpClient`, `HikvisionClient`, `DeviceManager`, `CallbackDeviceProvider`, `DigestAuthenticator` and `EventService`, covering XML/JSON parsing, URI and header building, device resolution, error classification and event pagination (31 to 88 tests).
- `EventService::between()`: iterates every access-control event in a time window as a generator, reusing one `searchID` across pages, advancing by the number of records actually returned, and stopping when the device stops reporting `MORE`. This is the primitive for backfilling events that webhook delivery dropped.
- `EventService::search()` accepts an optional `$searchId`, so callers that paginate can keep one search session across pages. Omitting it keeps the previous behaviour.
- `PersonService::all()` and `CardService::all()`: generators that walk the whole user or card list the way `EventService::between()` walks events — one `searchID` for the session, advancing by the number of records the device actually returned, stopping when it stops reporting `MORE`. A caller paging by hand could not know when to stop, because a single `search()` call discards the status field that says so.
- `PersonService::search()`, `CardService::search()` and `FingerprintService::search()` accept an optional `$searchId`, so a caller that paginates can hold one search session open.
- `Concerns\PagesSearchResults`: the one implementation of ISAPI search paging, now shared by events, people and cards so the three cannot drift into subtly different ideas of how it works.
- Exception taxonomy for retry decisions: `HikvisionException::isRetryable()`, `statusCode()` and `responseBody()`, with new `DeviceUnreachableException` (connection failures, retryable) and `DeviceBusyException` (HTTP 408/429/5xx, retryable). HTTP 401 now raises the existing `AuthenticationException`.

### Fixed
- **`searchID` is no longer taken from the wall clock in `PersonService`, `CardService` and `FingerprintService`.** ISAPI treats `searchID` as the identity of a search *session*, and every page of one search has to carry the same value. These services sent `(string) time()`, so two pages requested either side of a second boundary carried different ids — and a device seeing a new id starts a new search and serves the first page again. There is no error attached to that: the caller sees plausible records and simply never reaches the end of the list, or loops on the same page. Anything reconciling against a terminal was therefore reasoning about a roster it believed was complete and was not. `EventService` was already fixed; these three were not.
- **`EventService::count()` read only the flat `totalNum`.** The endpoint is documented to answer under `AcsEventTotalNum`, so on any device that follows the documentation this returned zero for every query — silently, with nothing to distinguish it from "no events". Both shapes are now read, and a numeric string is accepted.

### Changed
- Malformed XML from a device no longer raises a PHP warning: libxml errors are captured internally and the raw body is returned instead.
- `minimum-stability` lowered from `dev` to `stable` now that Laravel 13 is released.
- Updated `guzzlehttp/guzzle` to 7.15.5, `guzzlehttp/psr7` to 2.13.1 and `league/commonmark` to 2.10.0, clearing all advisories reported by `composer audit`.
- Whole codebase formatted with Pint (Laravel preset). No behavioural change.

## [1.4.0] - 2025-10-30

### Added
- **EventNotificationService**: New service for managing HTTP event notifications (webhooks)
- **Webhook Support**: Configure Hikvision devices to push events to HTTP endpoints in real-time
- **XML Format Support**: Added `putXml()` method to HikvisionClient for XML-only endpoints
- **HTTP Client XML Methods**:
  - `arrayToXml()`: Convert PHP arrays to Hikvision-compatible XML format
  - `xmlToArray()`: Parse XML responses to PHP arrays
  - Automatic XML/JSON format detection based on Content-Type headers
- **Event Notification Methods**:
  - `configureWebhook()`: Simplified webhook setup with single method call
  - `configureHttpHost()`: Advanced webhook configuration with authentication
  - `getHttpHost()` / `getAllHttpHosts()`: Retrieve webhook configurations
  - `enableHttpHost()` / `disableHttpHost()`: Toggle webhook status
  - `removeHttpHost()`: Delete webhook configuration
  - `testHttpNotification()`: Send test event to configured webhook
  - `getCapabilities()`: Get notification capabilities from device
- **Multi-Device Webhook Support**: Configure webhooks across multiple devices
- **Webhook Security**: HTTP Basic and Digest authentication support for webhooks
- **PHP Extensions**: Added `ext-simplexml` and `ext-libxml` requirements

### Changed
- **HttpClient**: Enhanced to handle both JSON and XML request/response formats
- **HikvisionClient**: Added dedicated `putXml()` method for XML-required endpoints
- **HTTP Request Handling**: Automatic format detection and conversion

### Features
- 🔔 **Real-time events**: Receive instant notifications from devices
- 🔒 **Secure webhooks**: Authentication support (Basic, Digest, or none)
- 🏢 **Multi-device**: Configure webhooks for unlimited devices
- 📨 **Event types**: Filter specific event types or receive all events
- 🧪 **Testing**: Built-in webhook testing functionality
- ⚡ **Performance**: Push-based events eliminate polling overhead
- 🔄 **Backward compatible**: Existing code works without changes

### Documentation
- Added comprehensive webhook setup guide to README.md
- Added EventNotificationService to Services Overview
- Added webhook controller examples for receiving events
- Added multi-device webhook configuration examples
- Added webhook security best practices
- Updated Table of Contents with webhook section

## [1.3.0] - 2025-10-13

### Added
- **DeviceProviderInterface**: New contract for universal device configuration loading
- **ConfigDeviceProvider**: Default provider for config-based devices (backward compatible)
- **DatabaseDeviceProvider**: Load terminals from database tables with caching
- **CallbackDeviceProvider**: Maximum flexibility - load devices from any source (API, Redis, etc.)
- **Multi-Tenant Support**: Device providers support tenant-scoped device loading
- **Runtime Device Registration**: Register devices dynamically with `registerDevice()`
- **Provider Switching**: Change device provider at runtime with `setProvider()`
- **Device Reloading**: Reload devices from source with `reload()` method
- **Cache Management**: Built-in caching for database providers with TTL support

### Changed
- **DeviceManager**: Now accepts `DeviceProviderInterface` instead of raw config array
- **Service Provider**: Auto-detects custom device provider via binding `hikvision.device.provider`
- **Architecture**: Implements Strategy Pattern for device loading

### Features
- 🗄️ **Database-driven terminals**: Load terminals from database dynamically
- 🏢 **Multi-tenant ready**: Scope terminals by tenant/company
- 🔄 **Hot reload**: Update terminals in DB and reload without restart
- 🎯 **Flexible providers**: Config, Database, Callback, or custom implementations
- ⚡ **Performance**: Built-in caching with configurable TTL
- 🔒 **Backward compatible**: Existing config-based setup works without changes

### Documentation
- Added comprehensive "Loading Devices from Database" section to README.md
- Added multi-tenant support examples
- Added Eloquent model integration examples
- Documented provider switching and runtime registration
- Updated architecture documentation in CLAUDE.md

## [1.2.0] - 2025-10-13

### Added
- **DeviceManager**: New class for managing multiple Hikvision devices simultaneously
- **Hikvision Facade**: New facade (`Shaykhnazar\HikvisionIsapi\Facades\Hikvision`) for multi-device access
- **Multi-Device Support**: Configure and manage unlimited Hikvision devices via configuration
- **Device Discovery**: Added `availableDevices()` method to list all configured devices
- **Device Validation**: Added `hasDevice()` method to check if device exists in configuration
- **Client Caching**: Device-specific client instances are cached for performance
- **Environment Variables**: Support for device-specific environment variables pattern (e.g., `HIKVISION_ENTRANCE_IP`)

### Changed
- **Service Provider**: Updated to register `DeviceManager` as singleton
- **Configuration**: Enhanced `hikvision.php` config with multi-device examples and documentation
- **HikvisionClient**: Now resolves from `DeviceManager::default()` for backward compatibility

### Documentation
- Added comprehensive multi-device support section to README.md
- Added usage examples for syncing employees to multiple devices
- Updated CLAUDE.md with DeviceManager architecture details
- Added environment variable patterns documentation
- Documented backward compatibility approach

### Backward Compatibility
- ✅ **100% backward compatible** - Existing single-device code works without changes
- Default device behavior preserved for apps using single device
- Services continue to work with dependency injection as before

## [1.1.0] - 2025-10-13

### Added
- **FaceService**: New `searchFace()` method for searching face data with pagination support
- **FaceService**: New `deleteFaceSearch()` method for deleting face search data by FDID
- **FaceService**: New `uploadFaceDataRecord()` method for uploading face images with multipart/form-data
- **HttpClient**: Added `postMultipart()` method to support file uploads
- **HttpClientInterface**: Extended interface with `postMultipart()` method
- **HikvisionClient**: Added `postMultipart()` wrapper method for multipart form data requests

### Changed
- **PersonService**: Updated delete endpoint from `/ISAPI/AccessControl/UserInfoDetail/Delete` to `/ISAPI/AccessControl/UserInfo/Delete` to match official ISAPI specification
- **PersonService**: Updated delete request body structure from `UserInfoDetail` to `UserInfoDelCond` for better alignment with Hikvision API
- **PersonService**: Removed `deleteAll()` method (users should use `delete()` with appropriate parameters)

### Fixed
- **HikvisionClient**: Added validation to ensure username and password are provided in configuration
- **HikvisionClient**: Now throws clear error message when `HIKVISION_PASSWORD` is not set in environment
- **Tests**: Updated PersonServiceTest to match new delete endpoint

### Documentation
- Updated README.md with new FaceService methods and usage examples
- Added comprehensive examples for face data search and management
- Updated CLAUDE.md with new implementation details
- Improved troubleshooting section in README

## [1.0.0] - 2025-10-09

### Added
- Initial release of Hikvision ISAPI Laravel package
- Device management service (getInfo, getStatus, getCapabilities, isOnline)
- Person management service with full CRUD operations
- Card management service with batch operations support
- Face recognition service for uploading and managing face images
- Fingerprint service for fingerprint registration and management
- Access control service for door operations
- Event service for searching and subscribing to events
- Immutable DTOs (Person, Card, Face) with readonly properties
- Enums for UserType and EventType (PHP 8.2+)
- Custom exception hierarchy (HikvisionException, AuthenticationException, etc.)
- Service provider with automatic service registration
- Laravel facade for easy API access
- Comprehensive documentation (README.md, CLAUDE.md)
- Full test suite (Unit tests for DTOs and Services, Feature tests for integration)
- Support for both Laravel 11 and Laravel 12
- PHP 8.2+ with modern features (readonly properties, enums, typed parameters)
- SOLID principles implementation throughout the codebase
- Digest authentication support
- Configurable multiple device support
- Batch operations for cards
- Pagination support for search operations

### Security
- Digest authentication for secure communication
- SSL/TLS support with configurable verification
- Environment-based credential management
- Input validation on all DTOs

## [0.1.0] - 2025-10-09

### Added
- Project initialization
- Basic package structure
- Composer configuration

---

## Release Notes

### v1.0.0

This is the first stable release of the Hikvision ISAPI Laravel package. It provides a complete, production-ready solution for integrating with Hikvision face recognition terminals and access control devices.

**Key Features:**
- 🎯 Clean Architecture with SOLID principles
- 🔒 Secure authentication with Digest auth
- 📦 7 comprehensive services covering all major ISAPI endpoints
- 🧪 Full test coverage with unit and integration tests
- 📖 Extensive documentation for developers and AI assistants
- 🚀 Laravel 11 & 12 support
- 💪 PHP 8.2+ with modern features

**Breaking Changes:**
None (initial release)

**Upgrade Guide:**
N/A (initial release)

---

For more information, see the [README.md](README.md) file.
