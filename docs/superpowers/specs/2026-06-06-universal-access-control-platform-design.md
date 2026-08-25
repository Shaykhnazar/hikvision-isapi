# Universal Access Control Platform Design

> **ARXIV / SUPERSEDED (2026-08-25).** Bu hujjat endi ijro uchun ustuvor emas. Uning tanqidiy tahlili va o'rniga qabul qilingan qarorlar: `docs/startup/` (ayniqsa `docs/startup/00-eski-rejaning-tahlili.md`). Hujjat to'g'ri prinsiplar manbasi sifatida saqlanmoqda.


Date: 2026-06-06
Status: Design approved for review
Repository context: Laravel package for Hikvision ISAPI access-control terminals

## Goal

Build a universal access-control platform on top of the Hikvision ISAPI package. The platform should support multiple customer segments without creating a separate product for each segment. The core product manages organizations, locations, devices, people, credentials, access rules, sync jobs, and access events. Segment-specific behavior is provided through presets and configuration.

The first version should be useful across offices, factories, schools, gyms, and business centers, while still staying small enough to ship and test in real customer environments.

## Product Positioning

The platform is sold differently per segment, but internally uses one engine.

- Office and factory: attendance, shift visibility, access logs, security alerts.
- Gym and subscription businesses: membership-based access enablement and blocking.
- Education: student attendance and guardian notifications.
- Business center: tenant access and visitor passes.
- Security teams: real-time access events, denied-entry alerts, and remote door control.

The UI should not expose a confusing "universal platform" concept to end users. A new organization starts from a preset, and the preset configures labels, default person fields, reports, and automation templates.

## MVP Scope

The MVP includes:

- Organization and tenant isolation.
- Branch/location management.
- Hikvision device registration and status checks.
- Door/access-point management.
- Person management for employees, students, members, and visitors.
- Credential management for face, card, and fingerprint records.
- Access groups that bind people to doors and schedules.
- Device sync for person and credential changes.
- Webhook receiver for real-time Hikvision access events.
- Access log dashboard and API.
- Remote door open/close/status actions.
- Basic automation rules.
- Initial segment presets for office, gym, and education.

The MVP excludes:

- Payroll calculation.
- Complex shift planning.
- Advanced billing and payment reconciliation.
- Visitor kiosk self-check-in.
- Native mobile apps.
- Multi-vendor device support beyond Hikvision.
- Advanced video analytics or camera-stream processing.

## Architecture

The platform has four main layers.

### Core Domain

The core domain owns tenant isolation, people, credentials, access policies, events, and automations. It should not contain segment-specific branching like "if gym" or "if school" in business-critical paths. Segment differences are expressed through presets, metadata, and rule configuration.

### Hikvision Integration

This layer wraps the existing package services:

- `DeviceService` for status, capabilities, and device information.
- `PersonService` for person add, update, search, and delete.
- `FaceService` for face library and face-image operations.
- `CardService` for card operations.
- `FingerprintService` for fingerprint operations.
- `AccessControlService` for remote door control and door status.
- `EventNotificationService` for configuring device webhooks.
- `EventService` for searching or subscribing to device events when needed.

The platform should treat Hikvision devices as eventually consistent external systems. Local database state is the source of desired access state; device sync jobs reconcile devices to that desired state.

### Automation Layer

Automations react to normalized access events and domain changes. Initial actions are intentionally narrow:

- Send Telegram notification.
- Send webhook to an external URL.
- Disable or enable access for a person.
- Mark attendance event.

Automation rules use conditions such as event type, person type, branch, door, time window, denied access, subscription expiry, or unknown credential.

### Segment Presets

Presets configure the product for a market segment without changing the core schema.

Office preset:

- Person type: employee.
- Default fields: department, role, employee number.
- Default reports: daily attendance, late arrivals, denied access.
- Default automations: denied access alert, after-hours access alert.

Gym preset:

- Person type: member.
- Default fields: membership plan, expiry date, active status.
- Default reports: daily check-ins, expired members, denied access.
- Default automations: disable access on expiry, notify staff on denied active member.

Education preset:

- Person type: student.
- Default fields: class/group, guardian contact, student number.
- Default reports: attendance by group, late arrivals, missing students.
- Default automations: guardian notification on arrival or departure.

Visitor and security presets are planned after the MVP.

## Core Data Model

The first implementation should use explicit relational tables for the universal core.

- `organizations`: customer tenants.
- `branches`: physical locations under an organization.
- `devices`: Hikvision terminal connection settings and status.
- `doors`: logical access points, mapped to device door numbers.
- `people`: universal identity table.
- `person_attributes`: optional segment-specific key-value fields.
- `credentials`: face, card, and fingerprint credentials for a person.
- `access_groups`: named access policies.
- `access_group_people`: people assigned to groups.
- `access_group_doors`: doors assigned to groups.
- `access_schedules`: allowed time windows for access groups.
- `device_sync_jobs`: desired changes to push to devices.
- `device_sync_job_items`: per-person or per-credential sync details.
- `access_events`: normalized event stream from devices.
- `automation_rules`: configured event/domain automations.
- `automation_executions`: audit log for automation runs.
- `segment_presets`: preset definitions available to organizations.

Important field choices:

- `people.type`: `employee`, `student`, `member`, `visitor`, or `custom`.
- `people.external_id`: stable ID from a customer system if imported.
- `credentials.type`: `face`, `card`, or `fingerprint`.
- `credentials.status`: `pending`, `synced`, `failed`, `disabled`.
- `devices.status`: `unknown`, `online`, `offline`, `error`.
- `device_sync_jobs.status`: `queued`, `running`, `succeeded`, `partial`, `failed`.
- `access_events.event_type`: normalized type such as `access_granted`, `access_denied`, `door_opened`, `door_closed`, `unknown`.

Face images and biometric payloads should not be logged. If retained locally, they must be encrypted and tied to a retention policy.

## Primary Workflows

### Organization Setup

1. Admin creates an organization.
2. Admin chooses a segment preset.
3. Platform creates default labels, person fields, reports, and automation templates.
4. Admin creates branches and devices.
5. Platform checks device reachability and capabilities.
6. Platform configures device webhook target if network access allows it.

### Person And Credential Sync

1. Admin creates or imports people.
2. Admin attaches credentials such as face, card, or fingerprint.
3. Admin assigns people to access groups.
4. Platform creates sync jobs for affected devices.
5. Worker pushes person and credential changes to Hikvision devices.
6. Sync status is stored per device and credential.
7. Failed sync items can be retried without duplicating people.

### Access Event Handling

1. Hikvision device sends a webhook event to the platform.
2. Receiver authenticates and parses XML or JSON payload.
3. Event is normalized to `access_events`.
4. Platform links the event to organization, branch, device, door, person, and credential when possible.
5. Automation rules are evaluated.
6. Dashboard and reports read from the normalized event stream.

### Subscription Or Eligibility Blocking

1. A rule determines that a person should no longer have access, such as expired membership.
2. Platform updates person or credential status locally.
3. Platform creates sync jobs for relevant devices.
4. Device access is disabled.
5. A status and audit entry are visible in the dashboard.

## Error Handling

The platform should classify errors by source.

- Device authentication errors: mark device as `error`, stop sync, show credential warning.
- Device offline or timeout: mark sync job as retryable and apply backoff.
- Unsupported capability: prevent unsupported credential actions for that device.
- Invalid biometric image or card data: reject before sync when possible.
- Duplicate person or card on device: reconcile by searching device state and updating local sync status.
- Webhook parse errors: store raw metadata only if safe, mark event as invalid, and avoid logging PII or biometric data.
- Automation errors: store failed execution and continue processing the access event.

No failed external call should corrupt the local desired state. Local state and sync status must be separate.

## Security And Compliance

The platform handles sensitive physical access and biometric data, so security must be part of the MVP.

- Tenant isolation is mandatory on every query and job.
- Device credentials are encrypted at rest.
- Webhook endpoints use per-device secrets or HTTP authentication where supported.
- Audit logs record admin access-control changes.
- Biometric data is never written to application logs.
- Face images are stored only when needed and are encrypted at rest.
- Retention rules define when biometric data and event logs are deleted.
- Admin roles separate owner, manager, security operator, and viewer permissions.
- Remote door actions require explicit audit logging.

For markets with biometric privacy rules, onboarding must include consent and retention-policy support before production rollout.

## API Surface

Initial API modules:

- `OrganizationsController`
- `BranchesController`
- `DevicesController`
- `DoorsController`
- `PeopleController`
- `CredentialsController`
- `AccessGroupsController`
- `DeviceSyncController`
- `AccessEventsController`
- `AutomationRulesController`
- `Webhook/HikvisionEventsController`

The API should expose segment-neutral resources. Preset-specific UI labels can be resolved in frontend configuration.

## UI Structure

The first dashboard should be operational and dense, not marketing-oriented.

- Organization switcher if the authenticated user belongs to multiple organizations.
- Branch and device status overview.
- People table with filters by type, group, branch, status, and sync state.
- Person detail page with credentials, access groups, events, and sync history.
- Access groups page with people, doors, and schedule assignment.
- Access event log with filters and export.
- Device detail page with status, capabilities, doors, webhook status, and sync actions.
- Automation rules page with simple condition/action forms.
- Preset setup screen shown during organization onboarding.

## Testing Strategy

The first implementation should include:

- Unit tests for access-policy calculations.
- Unit tests for segment preset creation.
- Unit tests for Hikvision webhook payload parsing and normalization.
- Feature tests for tenant isolation across people, devices, and events.
- Feature tests for person and credential CRUD.
- Feature tests for access-group assignment.
- Feature tests for sync-job creation.
- Feature tests for automation rule execution.
- Integration-style tests with mocked Hikvision package services.

Real device tests should be manual at first and documented as a release checklist because Hikvision terminal behavior can vary by model and firmware.

## Rollout Plan

Phase 1: Universal core and office preset.

- Organization, branch, device, people, access groups, events, and basic sync.
- Telegram alert automation.
- Device status and webhook receiver.

Phase 2: Gym and education presets.

- Membership expiry rule.
- Student group fields.
- Attendance reports per preset.
- Preset-specific dashboard labels.

Phase 3: Hardening and sales pilots.

- Device compatibility matrix.
- Consent and retention settings.
- Export/report improvements.
- Pilot deployments across office, gym, and education customers.

## Success Criteria

The MVP is successful when:

- One codebase can onboard at least three segment presets without schema changes.
- A Hikvision device can be registered, checked, and configured for events.
- A person can be created with card or face credential and synced to a device.
- Access events are received and normalized in real time.
- Operators can see who entered, when, where, and by which credential.
- A simple automation can notify staff or disable access based on a rule.
- Tenant data is isolated in API responses, jobs, and event processing.
