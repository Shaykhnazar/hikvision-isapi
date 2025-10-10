<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\DTOs;

use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\DTOs\Person;
use Shaykhnazar\HikvisionIsapi\Enums\UserType;

class PersonTest extends TestCase
{
    public function test_can_create_person_dto(): void
    {
        $person = new Person(
            employeeNo: 'EMP001',
            name: 'John Doe',
            userType: UserType::NORMAL,
            validEnabled: true,
            beginTime: '2025-01-01T00:00:00',
            endTime: '2025-12-31T23:59:59'
        );

        $this->assertSame('EMP001', $person->employeeNo);
        $this->assertSame('John Doe', $person->name);
        $this->assertSame(UserType::NORMAL, $person->userType);
        $this->assertTrue($person->validEnabled);
        $this->assertSame('2025-01-01T00:00:00', $person->beginTime);
        $this->assertSame('2025-12-31T23:59:59', $person->endTime);
    }

    public function test_can_convert_person_to_array(): void
    {
        $person = new Person(
            employeeNo: 'EMP001',
            name: 'John Doe',
            userType: UserType::NORMAL,
            validEnabled: true,
            beginTime: '2025-01-01T00:00:00',
            endTime: '2025-12-31T23:59:59',
            doorRight: '1',
            email: 'john@example.com'
        );

        $array = $person->toArray();

        $this->assertArrayHasKey('UserInfo', $array);
        $this->assertSame('EMP001', $array['UserInfo']['employeeNo']);
        $this->assertSame('John Doe', $array['UserInfo']['name']);
        $this->assertSame('normal', $array['UserInfo']['userType']);
        $this->assertTrue($array['UserInfo']['Valid']['enable']);
        $this->assertSame('john@example.com', $array['UserInfo']['email']);
    }

    public function test_can_create_person_from_array(): void
    {
        $data = [
            'UserInfo' => [
                'employeeNo' => 'EMP001',
                'name' => 'John Doe',
                'userType' => 'normal',
                'Valid' => [
                    'enable' => true,
                    'beginTime' => '2025-01-01T00:00:00',
                    'endTime' => '2025-12-31T23:59:59',
                ],
                'doorRight' => '1',
                'email' => 'john@example.com',
            ],
        ];

        $person = Person::fromArray($data);

        $this->assertInstanceOf(Person::class, $person);
        $this->assertSame('EMP001', $person->employeeNo);
        $this->assertSame('John Doe', $person->name);
        $this->assertSame(UserType::NORMAL, $person->userType);
        $this->assertTrue($person->validEnabled);
        $this->assertSame('john@example.com', $person->email);
    }

    public function test_person_with_right_plan(): void
    {
        $person = new Person(
            employeeNo: 'EMP001',
            name: 'John Doe',
            userType: UserType::NORMAL,
            validEnabled: true,
            rightPlan: [
                ['doorNo' => 1, 'planTemplateNo' => '1'],
                ['doorNo' => 2, 'planTemplateNo' => '1'],
            ]
        );

        $array = $person->toArray();

        $this->assertArrayHasKey('RightPlan', $array['UserInfo']);
        $this->assertCount(2, $array['UserInfo']['RightPlan']);
        $this->assertSame(1, $array['UserInfo']['RightPlan'][0]['doorNo']);
    }

    public function test_person_is_readonly(): void
    {
        $person = new Person(
            employeeNo: 'EMP001',
            name: 'John Doe',
            userType: UserType::NORMAL,
            validEnabled: true
        );

        $reflection = new \ReflectionClass($person);
        $this->assertTrue($reflection->isReadOnly());
    }
}
