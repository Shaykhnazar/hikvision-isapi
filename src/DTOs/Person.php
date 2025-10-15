<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\DTOs;

use Shaykhnazar\HikvisionIsapi\Enums\UserType;

final readonly class Person
{
    public function __construct(
        public string $employeeNo,
        public string $name,
        public UserType $userType,
        public bool $validEnabled,
        public ?string $beginTime = null,
        public ?string $endTime = null,
        public ?string $doorRight = null,
        public array $rightPlan = [],
        public ?string $email = null,
        public ?string $phoneNumber = null,
        public ?int $organizationId = null,
        public ?array $belongGroup = null
    ) {}

    public function toArray(): array
    {
        // Build Valid object - all fields are required
        $valid = ['enable' => $this->validEnabled];
        if ($this->beginTime !== null) {
            $valid['beginTime'] = $this->beginTime;
        }
        if ($this->endTime !== null) {
            $valid['endTime'] = $this->endTime;
        }

        $userInfo = [
            'employeeNo' => $this->employeeNo,
            'name' => $this->name,
            'userType' => $this->userType->value,
            'Valid' => $valid,
        ];

        // Add optional fields only if they have values
        if ($this->doorRight !== null) {
            $userInfo['doorRight'] = $this->doorRight;
        }

        if (!empty($this->rightPlan)) {
            $userInfo['RightPlan'] = $this->rightPlan;
        }

        if ($this->email !== null) {
            $userInfo['email'] = $this->email;
        }

        if ($this->phoneNumber !== null) {
            $userInfo['phoneNumber'] = $this->phoneNumber;
        }

        if ($this->organizationId !== null) {
            $userInfo['organizationId'] = $this->organizationId;
        }

        if ($this->belongGroup !== null) {
            $userInfo['belongGroup'] = $this->belongGroup;
        }

        return ['UserInfo' => $userInfo];
    }

    public static function fromArray(array $data): self
    {
        $userInfo = $data['UserInfo'] ?? $data;

        return new self(
            employeeNo: $userInfo['employeeNo'] ?? '',
            name: $userInfo['name'] ?? '',
            userType: UserType::from($userInfo['userType'] ?? 'normal'),
            validEnabled: $userInfo['Valid']['enable'] ?? true,
            beginTime: $userInfo['Valid']['beginTime'] ?? null,
            endTime: $userInfo['Valid']['endTime'] ?? null,
            doorRight: $userInfo['doorRight'] ?? null,
            rightPlan: $userInfo['RightPlan'] ?? [],
            email: $userInfo['email'] ?? null,
            phoneNumber: $userInfo['phoneNumber'] ?? null,
            organizationId: $userInfo['organizationId'] ?? null,
            belongGroup: $userInfo['belongGroup'] ?? null
        );
    }
}
