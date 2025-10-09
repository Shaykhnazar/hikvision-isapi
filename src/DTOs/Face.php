<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\DTOs;

final readonly class Face
{
    public function __construct(
        public string $employeeNo,
        public string $faceData,
        public ?int $faceLibId = 1,
        public ?string $faceLibType = 'blackFD'
    ) {}

    public function toArray(): array
    {
        return [
            'faceInfo' => [
                'employeeNo' => $this->employeeNo,
                'faceLibType' => $this->faceLibType,
            ],
            'faceData' => $this->faceData,
        ];
    }

    public static function fromArray(array $data): self
    {
        $faceInfo = $data['faceInfo'] ?? $data;

        return new self(
            employeeNo: $faceInfo['employeeNo'] ?? '',
            faceData: $data['faceData'] ?? '',
            faceLibId: $faceInfo['faceLibId'] ?? 1,
            faceLibType: $faceInfo['faceLibType'] ?? 'blackFD'
        );
    }
}
