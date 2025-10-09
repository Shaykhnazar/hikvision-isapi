<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\DTOs;

final readonly class Card
{
    public function __construct(
        public string $employeeNo,
        public string $cardNo,
        public ?string $cardType = null,
        public bool $enabled = true
    ) {}

    public function toArray(): array
    {
        return [
            'CardInfo' => [
                'employeeNo' => $this->employeeNo,
                'cardNo' => $this->cardNo,
                'cardType' => $this->cardType,
            ],
        ];
    }

    public static function fromArray(array $data): self
    {
        $cardInfo = $data['CardInfo'] ?? $data;

        return new self(
            employeeNo: $cardInfo['employeeNo'] ?? '',
            cardNo: $cardInfo['cardNo'] ?? '',
            cardType: $cardInfo['cardType'] ?? null,
            enabled: $cardInfo['enabled'] ?? true
        );
    }
}
