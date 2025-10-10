<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\DTOs;

use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\DTOs\Card;

class CardTest extends TestCase
{
    public function test_can_create_card_dto(): void
    {
        $card = new Card(
            employeeNo: 'EMP001',
            cardNo: '1234567890',
            cardType: 'normal',
            enabled: true
        );

        $this->assertSame('EMP001', $card->employeeNo);
        $this->assertSame('1234567890', $card->cardNo);
        $this->assertSame('normal', $card->cardType);
        $this->assertTrue($card->enabled);
    }

    public function test_can_convert_card_to_array(): void
    {
        $card = new Card(
            employeeNo: 'EMP001',
            cardNo: '1234567890',
            cardType: 'normal'
        );

        $array = $card->toArray();

        $this->assertArrayHasKey('CardInfo', $array);
        $this->assertSame('EMP001', $array['CardInfo']['employeeNo']);
        $this->assertSame('1234567890', $array['CardInfo']['cardNo']);
        $this->assertSame('normal', $array['CardInfo']['cardType']);
    }

    public function test_can_create_card_from_array(): void
    {
        $data = [
            'CardInfo' => [
                'employeeNo' => 'EMP001',
                'cardNo' => '1234567890',
                'cardType' => 'normal',
                'enabled' => true,
            ],
        ];

        $card = Card::fromArray($data);

        $this->assertInstanceOf(Card::class, $card);
        $this->assertSame('EMP001', $card->employeeNo);
        $this->assertSame('1234567890', $card->cardNo);
        $this->assertSame('normal', $card->cardType);
        $this->assertTrue($card->enabled);
    }

    public function test_card_has_default_values(): void
    {
        $card = new Card(
            employeeNo: 'EMP001',
            cardNo: '1234567890'
        );

        $this->assertNull($card->cardType);
        $this->assertTrue($card->enabled);
    }

    public function test_card_is_readonly(): void
    {
        $card = new Card(
            employeeNo: 'EMP001',
            cardNo: '1234567890'
        );

        $reflection = new \ReflectionClass($card);
        $this->assertTrue($reflection->isReadOnly());
    }
}
