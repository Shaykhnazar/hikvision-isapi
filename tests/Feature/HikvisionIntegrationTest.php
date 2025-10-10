<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Feature;

use Shaykhnazar\HikvisionIsapi\DTOs\Card;
use Shaykhnazar\HikvisionIsapi\DTOs\Person;
use Shaykhnazar\HikvisionIsapi\Enums\UserType;
use Shaykhnazar\HikvisionIsapi\Services\CardService;
use Shaykhnazar\HikvisionIsapi\Services\DeviceService;
use Shaykhnazar\HikvisionIsapi\Services\PersonService;
use Shaykhnazar\HikvisionIsapi\Tests\TestCase;

/**
 * Feature tests that actually connect to a Hikvision device.
 *
 * These tests are skipped by default. To run them:
 * 1. Configure your device credentials in phpunit.xml or .env
 * 2. Run: vendor/bin/phpunit --group integration
 *
 * @group integration
 */
class HikvisionIntegrationTest extends TestCase
{
    private DeviceService $deviceService;
    private PersonService $personService;
    private CardService $cardService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deviceService = app(DeviceService::class);
        $this->personService = app(PersonService::class);
        $this->cardService = app(CardService::class);

        // Skip tests if device is not accessible
        if (!$this->deviceService->isOnline()) {
            $this->markTestSkipped('Hikvision device is not online. Skipping integration tests.');
        }
    }

    public function test_can_connect_to_device(): void
    {
        $isOnline = $this->deviceService->isOnline();

        $this->assertTrue($isOnline, 'Device should be online');
    }

    public function test_can_get_device_info(): void
    {
        $info = $this->deviceService->getInfo();

        $this->assertArrayHasKey('DeviceInfo', $info);
        $this->assertNotEmpty($info['DeviceInfo']);
    }

    public function test_can_count_persons(): void
    {
        $count = $this->personService->count();

        $this->assertIsInt($count);
        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_complete_person_workflow(): void
    {
        $testEmployeeNo = 'TEST_' . time();

        // 1. Create person
        $person = new Person(
            employeeNo: $testEmployeeNo,
            name: 'Test User',
            userType: UserType::NORMAL,
            validEnabled: true,
            beginTime: now()->toISOString(),
            endTime: now()->addYear()->toISOString()
        );

        $addResponse = $this->personService->add($person);
        $this->assertArrayHasKey('statusCode', $addResponse);

        // 2. Search for person
        $persons = $this->personService->search(0, 100);
        $found = collect($persons)->first(fn($p) => $p->employeeNo === $testEmployeeNo);
        $this->assertNotNull($found, 'Person should be found in search results');

        // 3. Update person
        $updatedPerson = new Person(
            employeeNo: $testEmployeeNo,
            name: 'Test User Updated',
            userType: UserType::NORMAL,
            validEnabled: true,
            beginTime: now()->toISOString(),
            endTime: now()->addYear()->toISOString()
        );

        $updateResponse = $this->personService->update($updatedPerson);
        $this->assertArrayHasKey('statusCode', $updateResponse);

        // 4. Delete person
        $deleteResponse = $this->personService->delete([$testEmployeeNo]);
        $this->assertArrayHasKey('statusCode', $deleteResponse);
    }

    public function test_complete_card_workflow(): void
    {
        $testEmployeeNo = 'TEST_CARD_' . time();
        $testCardNo = 'CARD_' . time();

        // 1. Create person first
        $person = new Person(
            employeeNo: $testEmployeeNo,
            name: 'Test Card User',
            userType: UserType::NORMAL,
            validEnabled: true
        );

        $this->personService->add($person);

        // 2. Add card
        $card = new Card(
            employeeNo: $testEmployeeNo,
            cardNo: $testCardNo,
            cardType: 'normal'
        );

        $addCardResponse = $this->cardService->add($card);
        $this->assertArrayHasKey('statusCode', $addCardResponse);

        // 3. Search for card
        $cards = $this->cardService->search(0, 100, $testEmployeeNo);
        $found = collect($cards)->first(fn($c) => $c->cardNo === $testCardNo);
        $this->assertNotNull($found, 'Card should be found in search results');

        // 4. Cleanup
        $this->cardService->delete([$testEmployeeNo]);
        $this->personService->delete([$testEmployeeNo]);
    }

    public function test_batch_card_operations(): void
    {
        $testEmployeeNos = [
            'BATCH_TEST_1_' . time(),
            'BATCH_TEST_2_' . time(),
            'BATCH_TEST_3_' . time(),
        ];

        // Create persons first
        foreach ($testEmployeeNos as $employeeNo) {
            $person = new Person(
                employeeNo: $employeeNo,
                name: "Batch Test {$employeeNo}",
                userType: UserType::NORMAL,
                validEnabled: true
            );
            $this->personService->add($person);
        }

        // Batch add cards
        $cards = array_map(
            fn($no) => new Card($no, 'CARD_' . $no),
            $testEmployeeNos
        );

        $results = $this->cardService->batchAdd($cards);

        $this->assertSame(3, $results['total']);
        $this->assertGreaterThan(0, $results['success']);
        $this->assertEmpty($results['errors']);

        // Cleanup
        $this->cardService->delete($testEmployeeNos);
        $this->personService->delete($testEmployeeNos);
    }
}
