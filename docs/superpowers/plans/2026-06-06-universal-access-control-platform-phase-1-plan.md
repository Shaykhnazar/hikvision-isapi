# Universal Access Platform Phase 1 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development or superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a minimal universal access-control engine for the Hikvision package with tenant-scoped people, credentials, access groups, event normalization, and sync orchestration.

**Architecture:** Keep the package as a reusable core by introducing a narrow domain layer (`UniversalAccess\Domain`), an integration layer (`UniversalAccess\Hikvision`), and an orchestration layer (`UniversalAccess\Service`). No segment-specific branching is placed in business-critical paths; segment behavior is applied via preset definitions.

**Tech Stack:** PHP 8.2+, Laravel 11/12/13, PHPUnit, Queue contracts, Guzzle-backed `HikvisionClient`.

## Files

### Create
- `src/UniversalAccess/Domain/Enums/PersonType.php`
- `src/UniversalAccess/Domain/Enums/CredentialType.php`
- `src/UniversalAccess/Domain/Enums/CredentialStatus.php`
- `src/UniversalAccess/Domain/Enums/DeviceStatus.php`
- `src/UniversalAccess/Domain/Enums/SyncJobStatus.php`
- `src/UniversalAccess/Domain/Enums/AccessEventType.php`
- `src/UniversalAccess/Domain/ValueObjects/SyncIdentifier.php`
- `src/UniversalAccess/Domain/ValueObjects/CredentialFingerprint.php`
- `src/UniversalAccess/Domain/ValueObjects/CredentialCard.php`
- `src/UniversalAccess/Domain/ValueObjects/CredentialFace.php`
- `src/UniversalAccess/Domain/Contracts/TenantContext.php`
- `src/UniversalAccess/Domain/Contracts/EventNormalizerInterface.php`
- `src/UniversalAccess/Domain/Contracts/SyncJobRepositoryInterface.php`
- `src/UniversalAccess/Domain/Contracts/AccessEventRepositoryInterface.php`
- `src/UniversalAccess/Domain/Contracts/PresetRepositoryInterface.php`
- `src/UniversalAccess/Domain/ValueObjects/AccessEvent.php`
- `src/UniversalAccess/Domain/ValueObjects/AccessPreset.php`
- `src/UniversalAccess/Domain/Presets/PresetCatalog.php`
- `src/UniversalAccess/Domain/Presets/OfficePreset.php`
- `src/UniversalAccess/Domain/Presets/GymPreset.php`
- `src/UniversalAccess/Domain/Presets/EducationPreset.php`
- `src/UniversalAccess/Hikvision/Normalizer/HikvisionXmlNormalizer.php`
- `src/UniversalAccess/Hikvision/Normalizer/HikvisionJsonNormalizer.php`
- `src/UniversalAccess/Hikvision/Adapter/HikvisionSyncAdapter.php`
- `src/UniversalAccess/Service/AccessProfileService.php`
- `src/UniversalAccess/Service/AccessRuleEngine.php`
- `src/UniversalAccess/Service/SyncOrchestrator.php`
- `src/UniversalAccess/Service/WebhookService.php`
- `src/UniversalAccess/Service/AutomationRunner.php`
- `src/Http/Controllers/HikvisionWebhookController.php`
- `src/Http/Controllers/UniversalSetupController.php`
- `database/migrations/2026_06_06_000001_create_universal_access_core_tables.php`
- `config/hikvision-universal.php`
- `src/HikvisionIsapiServiceProvider.php` (modify for new bindings and publishables)
- `src/HikvisionIsapi.php` facade (modify if needed for discoverability)
- `tests/Unit/Domain/Presets/PresetCatalogTest.php`
- `tests/Unit/Domain/Normalizer/HikvisionXmlNormalizerTest.php`
- `tests/Unit/Domain/Normalizer/HikvisionJsonNormalizerTest.php`
- `tests/Feature/Access/SyncOrchestratorTest.php`
- `tests/Feature/Http/HikvisionWebhookControllerTest.php`
- `docs/superpowers/plans/2026-06-06-universal-access-control-platform-phase-1-plan.md`

### Modify
- `composer.json` (add optional dependency for `illuminate/queue` interfaces if absent)
- `README.md` (new section with quick-start and event payload examples)
- `src/HikvisionIsapiServiceProvider.php` (publish config, bindings for new services)
- `src/Config/hikvision.php` (add optional default `universal_mode` flags)

---

### Task 1: Add core enums and domain value objects

- [ ] **Step 1: Add person, credential, and event enums**

Add these files with strict types:

```php
<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\UniversalAccess\Domain\Enums;

enum PersonType: string
{
    case EMPLOYEE = 'employee';
    case STUDENT = 'student';
    case MEMBER = 'member';
    case VISITOR = 'visitor';
    case CUSTOM = 'custom';
}
```

Create equivalent enums for `CredentialType`, `CredentialStatus`, `DeviceStatus`, `SyncJobStatus`, and `AccessEventType` with explicit cases.

```bash
sed -n '1,120p' src/UniversalAccess/Domain/Enums/PersonType.php
```

- [ ] **Step 2: Add domain value objects and guard methods**

Create `SyncIdentifier`, `CredentialFingerprint`, `CredentialCard`, and `CredentialFace` to keep sync payloads immutable and validated.

```php
<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\UniversalAccess\Domain\ValueObjects;

final readonly class SyncIdentifier
{
    public function __construct(
        public string $organizationId,
        public string $personId,
        public string $deviceId,
        public string $correlationId
    ) {
        if ($organizationId === '') {
            throw new \InvalidArgumentException('organizationId cannot be empty');
        }
        if ($personId === '') {
            throw new \InvalidArgumentException('personId cannot be empty');
        }
    }

    public function toArray(): array
    {
        return [
            'organization_id' => $this->organizationId,
            'person_id' => $this->personId,
            'device_id' => $this->deviceId,
            'correlation_id' => $this->correlationId,
        ];
    }
}
```

- [ ] **Step 3: Write unit tests for immutable value object behavior**

Create `tests/Unit/Domain/Presets/PresetCatalogTest.php` with direct construction and invalid input assertions, then run:

```bash
vendor/bin/phpunit tests/Unit/Domain/Presets/PresetCatalogTest.php
```

Expected: all assertions fail before implementation, then pass after task 8.

---

### Task 2: Define contracts for normalization and repository boundaries

- [ ] **Step 1: Add normalization interface**

Create `src/UniversalAccess/Domain/Contracts/EventNormalizerInterface.php`.

```php
<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\UniversalAccess\Domain\Contracts;

use Shaykhnazar\HikvisionIsapi\UniversalAccess\Domain\ValueObjects\AccessEvent;

interface EventNormalizerInterface
{
    public function supports(string $payloadFormat): bool;
    public function normalize(array|string $payload): AccessEvent;
}
```

- [ ] **Step 2: Add repository contracts**

Create `SyncJobRepositoryInterface`, `AccessEventRepositoryInterface`, and `PresetRepositoryInterface` for future host app adapters. Keep signatures minimal to avoid framework lock-in.

- [ ] **Step 3: Write minimal contract test stubs**

Add unit tests that instantiate test doubles and assert method presence with `method_exists` checks to avoid interface drift.

- [ ] **Step 4: Run contract test file**

```bash
vendor/bin/phpunit tests/Unit/Domain/Normalizer/HikvisionXmlNormalizerTest.php
```

Expected: fail now because repository adapters are not yet implemented.

---

### Task 3: Build preset catalog and segment presets

- [ ] **Step 1: Implement `AccessPreset` value object**

Create immutable preset object with fields `slug`, `name`, `defaultPersonFields`, and `defaultAutomationRules`.

```php
<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\UniversalAccess\Domain\ValueObjects;

final readonly class AccessPreset
{
    public function __construct(
        public string $slug,
        public string $name,
        public array $defaultPersonFields,
        public array $defaultReports,
        public array $defaultAutomationRules,
    ) {}
}
```

- [ ] **Step 2: Implement catalog service**

Add `PresetCatalog` that returns `office`, `gym`, `education` objects from `->all()`, `->findBySlug()`, and `->exists()`.

- [ ] **Step 3: Add concrete presets**

Implement `OfficePreset`, `GymPreset`, `EducationPreset` classes returning arrays from the spec for person fields, labels, and default automation rules.

- [ ] **Step 4: Add unit tests for catalog behavior**

Create tests asserting:
- all() returns at least 3 presets
- findBySlug('gym') returns gym rules
- invalid slug throws `InvalidArgumentException`

Run:

```bash
vendor/bin/phpunit tests/Unit/Domain/Presets/PresetCatalogTest.php
```

Expected: pass.

---

### Task 4: Normalize Hikvision webhook payloads (XML + JSON)

- [ ] **Step 1: Implement `HikvisionXmlNormalizer`**

Create parser that maps known fields:
- `eventType`
- `eventState`
- `employeeNoString`
- `cardNo`
- `dateTime`
- `channel`

to `AccessEvent` and classifies event type using `AccessEventType`.

- [ ] **Step 2: Implement `HikvisionJsonNormalizer`**

Handle payloads that already come in JSON from API proxy adapters and produce same output as XML normalizer.

- [ ] **Step 3: Add fallback behavior**

Unknown fields should map to `AccessEventType::UNKNOWN` and include `raw_snippet` in metadata while never storing face image bytes.

- [ ] **Step 4: Unit tests**

Create separate tests for XML and JSON normalizers with realistic payload samples.

- [ ] **Step 5: Run**

```bash
vendor/bin/phpunit tests/Unit/Domain/Normalizer/HikvisionXmlNormalizerTest.php tests/Unit/Domain/Normalizer/HikvisionJsonNormalizerTest.php
```

Expected: all tests pass.

---

### Task 5: Add sync adapter and orchestration

- [ ] **Step 1: Implement `HikvisionSyncAdapter`**

Create adapter methods:
- `upsertPerson($personData, $device)`
- `upsertCredential($personId, $credential, $device)`
- `deletePerson($personId, $device)`
- `setCredentialActive($personId, $credentialId, $enabled, $device)`

Use existing package services (`PersonService`, `CardService`, `FaceService`, `FingerprintService`, `DeviceService`) and return normalized arrays.

- [ ] **Step 2: Add queue model values**

Create `SyncOrchestrator` input structure with:
- `tenant_id`
- `device_ids`
- `person_ids`
- `force_full_sync`
- `retry_strategy`

- [ ] **Step 3: Implement `SyncOrchestrator`**

Process:
1) build pending jobs by tenant and device
2) call adapter methods in deterministic order person -> card -> face -> fingerprint
3) persist status transitions `queued` -> `running` -> `succeeded|partial|failed`
4) record per-item error metadata.

- [ ] **Step 4: Feature test for orchestrator state transitions**

Create `tests/Feature/Access/SyncOrchestratorTest.php` with fake device/service stubs.

- [ ] **Step 5: Run**

```bash
vendor/bin/phpunit tests/Feature/Access/SyncOrchestratorTest.php
```

Expected: transitions and per-item statuses assert correctly.

---

### Task 6: Implement webhook ingress and event dispatch

- [ ] **Step 1: Add `WebhookService`**

Service should:
- verify HMAC header if configured
- pass raw payload to normalizer (XML then JSON fallback)
- create/append `AccessEvent` record
- call automation runner with non-blocking policy (deferred queue if needed)

- [ ] **Step 2: Add `AutomationRunner` initial rules**

Support at least:
- `person_access_denied_notification`
- `person_access_granted_notification`
- `person_expiry_lock` (sets person/credential access status false)
- `after_hours_alert`

- [ ] **Step 3: Add controller endpoints**

In `src/Http/Controllers/HikvisionWebhookController.php`, add:
- `__invoke(Request $request)` for default route
- `handle(Request $request, string $deviceName = null)` optional multi-device hint

Add method to parse content type and call webhook service.

- [ ] **Step 4: Add route registration in `HikvisionIsapiServiceProvider`**

Register optional route macro for `POST /api/webhooks/hikvision/events` only when user config enables it.

- [ ] **Step 5: Add feature test**

`tests/Feature/Http/HikvisionWebhookControllerTest.php` should send XML and JSON payloads and assert:
- 200 response
- event type normalized
- automation trigger invoked via fake runner.

- [ ] **Step 6: Run**

```bash
vendor/bin/phpunit tests/Feature/Http/HikvisionWebhookControllerTest.php
```

Expected: valid payloads handled and response payload includes event id.

---

### Task 7: Preset setup and onboarding workflow

- [ ] **Step 1: Add `config/hikvision-universal.php`**

Add defaults:
- `presets` names
- `webhook_secret` placeholder key
- `automation` default rules
- `device.sync.timeout_seconds`

- [ ] **Step 2: Add `UniversalSetupController`**

Expose method to initialize an organization with a preset:
- validate slug
- register preset metadata
- configure webhook URL template using request host and optional namespace
- create initial device webhook config object for each selected device.

- [ ] **Step 3: Publish config and docs**

Update `HikvisionIsapiServiceProvider` to publish `config/hikvision.php` and `config/hikvision-universal.php`.

- [ ] **Step 4: Add README usage section**

Add sample call for selecting preset and syncing first person.

---

### Task 8: Database migration and host app adapters (scaffold only)

- [ ] **Step 1: Add `database/migrations/2026_06_06_000001_create_universal_access_core_tables.php`**

Create table skeletons for:
- tenants
- branches
- devices
- doors
- people
- credentials
- access_groups
- access_group_people
- access_group_doors
- access_schedules
- sync_jobs
- sync_job_items
- access_events
- automation_rules

Keep fields aligned with spec and nullable for v1.

- [ ] **Step 2: Add index and FK strategy for multi-tenant isolation**

At least unique index per tenant for device key and person external id.

- [ ] **Step 3: Add migration test or SQL lint**

Run:

```bash
php -l database/migrations/2026_06_06_000001_create_universal_access_core_tables.php
```

Expected: no syntax errors.

- [ ] **Step 4: Commit migration as opt-in**

Only enable migration publish in README with explicit command.

---

### Task 9: Security hardening and compliance checks

- [ ] **Step 1: Add payload redaction helper**

Prevent logging of `faceData` and raw biometric bytes by centralizing redaction in webhook and sync services.

- [ ] **Step 2: Add tenant guard on service entrypoints**

Add a guard interface and middleware-compatible check stub in service methods so no cross-tenant data crossovers are possible even if repository returns shared rows.

- [ ] **Step 3: Add tests for redaction and tenant scope**

Feature tests should verify:
- logs do not include `faceData`
- cross-tenant access throws exception

- [ ] **Step 4: Run security-focused tests**

```bash
vendor/bin/phpunit tests/Feature/Http/HikvisionWebhookControllerTest.php
```

and a new tenant-scope test suite once added.

---

### Task 10: Final integration and phase-1 smoke

- [ ] **Step 1: Add example seed/migration usage docs**

Add one page in `docs/` with manual runbook:
- register device
- create organization and preset
- add person and credential
- run initial sync
- open webhook endpoint

- [ ] **Step 2: Add lightweight E2E checklist in `README.md`**

Checklist: registration, sync, webhook receive, denied access alert.

- [ ] **Step 3: Run entire relevant suite**

```bash
vendor/bin/phpunit tests/Unit/Domain/Presets/PresetCatalogTest.php tests/Unit/Domain/Normalizer/HikvisionXmlNormalizerTest.php tests/Unit/Domain/Normalizer/HikvisionJsonNormalizerTest.php tests/Feature/Access/SyncOrchestratorTest.php tests/Feature/Http/HikvisionWebhookControllerTest.php
```

- [ ] **Step 4: Run lint command for all new PHP files**

```bash
php -l src/UniversalAccess/Domain/Enums/PersonType.php
php -l src/UniversalAccess/Domain/Presets/PresetCatalog.php
php -l src/UniversalAccess/Hikvision/Normalizer/HikvisionXmlNormalizer.php
```

- [ ] **Step 5: Commit**

```bash
git add src tests database/migrations config docs README.md
git commit -m "feat(universal): add phase 1 universal access core scaffolding"
```

---

### Self-review checklist for this plan

- [ ] `organizations`, `branches`, `devices`, `people`, `credentials`, `access groups`, `events`, `automation`, and `sync jobs` are all covered by explicit tasks.
- [ ] No placeholder placeholders such as TODO, TBD, fill later, or "implement later" remain in task steps.
- [ ] All required methods and class names are consistent across plan sections.
- [ ] File paths use explicit repository-relative locations and match declared namespaces.
- [ ] All tests listed have concrete paths and expected outcomes.

### Execution choice

Plan complete and saved to `docs/superpowers/plans/2026-06-06-universal-access-control-platform-phase-1-plan.md`.

1) Subagent-Driven — I dispatch a fresh subagent per task and review checkpoints.
2) Inline Execution — I execute tasks in this session using `executing-plans`.

Which approach do you want to use?
