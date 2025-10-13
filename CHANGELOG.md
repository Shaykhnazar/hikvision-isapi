# Changelog

All notable changes to `hikvision-isapi` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
