<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\Services;

use Mockery;
use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;
use Shaykhnazar\HikvisionIsapi\DTOs\Person;
use Shaykhnazar\HikvisionIsapi\Enums\UserType;
use Shaykhnazar\HikvisionIsapi\Services\PersonService;

class PersonServiceTest extends TestCase
{
    private HikvisionClient $mockClient;
    private PersonService $personService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockClient = Mockery::mock(HikvisionClient::class);
        $this->personService = new PersonService($this->mockClient);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_get_person_count(): void
    {
        $this->mockClient
            ->shouldReceive('get')
            ->with('/ISAPI/AccessControl/UserInfo/Count')
            ->once()
            ->andReturn([
                'UserInfo' => [
                    'userNumber' => 42,
                ],
            ]);

        $count = $this->personService->count();

        $this->assertSame(42, $count);
    }

    public function test_can_add_person(): void
    {
        $person = new Person(
            employeeNo: 'EMP001',
            name: 'John Doe',
            userType: UserType::NORMAL,
            validEnabled: true
        );

        $this->mockClient
            ->shouldReceive('post')
            ->with('/ISAPI/AccessControl/UserInfo/Record', $person->toArray())
            ->once()
            ->andReturn(['statusCode' => 1]);

        $response = $this->personService->add($person);

        $this->assertArrayHasKey('statusCode', $response);
        $this->assertSame(1, $response['statusCode']);
    }

    public function test_can_update_person(): void
    {
        $person = new Person(
            employeeNo: 'EMP001',
            name: 'John Doe Updated',
            userType: UserType::NORMAL,
            validEnabled: true
        );

        $this->mockClient
            ->shouldReceive('put')
            ->with('/ISAPI/AccessControl/UserInfo/Modify', $person->toArray())
            ->once()
            ->andReturn(['statusCode' => 1]);

        $response = $this->personService->update($person);

        $this->assertArrayHasKey('statusCode', $response);
        $this->assertSame(1, $response['statusCode']);
    }

    public function test_can_delete_persons(): void
    {
        $employeeNos = ['EMP001', 'EMP002', 'EMP003'];

        $this->mockClient
            ->shouldReceive('put')
            ->with('/ISAPI/AccessControl/UserInfo/Delete', Mockery::type('array'))
            ->once()
            ->andReturn(['statusCode' => 1]);

        $response = $this->personService->delete($employeeNos);

        $this->assertArrayHasKey('statusCode', $response);
    }

    public function test_can_search_persons(): void
    {
        $mockResponse = [
            'UserInfoSearch' => [
                'UserInfo' => [
                    [
                        'employeeNo' => 'EMP001',
                        'name' => 'John Doe',
                        'userType' => 'normal',
                        'Valid' => [
                            'enable' => true,
                        ],
                    ],
                    [
                        'employeeNo' => 'EMP002',
                        'name' => 'Jane Smith',
                        'userType' => 'normal',
                        'Valid' => [
                            'enable' => true,
                        ],
                    ],
                ],
            ],
        ];

        $this->mockClient
            ->shouldReceive('post')
            ->with('/ISAPI/AccessControl/UserInfo/Search', Mockery::type('array'))
            ->once()
            ->andReturn($mockResponse);

        $persons = $this->personService->search(0, 30);

        $this->assertIsArray($persons);
        $this->assertCount(2, $persons);
        $this->assertInstanceOf(Person::class, $persons[0]);
        $this->assertSame('EMP001', $persons[0]->employeeNo);
    }

    public function test_can_get_capabilities(): void
    {
        $this->mockClient
            ->shouldReceive('get')
            ->with('/ISAPI/AccessControl/UserInfo/Capabilities')
            ->once()
            ->andReturn(['capabilities' => 'data']);

        $capabilities = $this->personService->getCapabilities();

        $this->assertArrayHasKey('capabilities', $capabilities);
    }
}
