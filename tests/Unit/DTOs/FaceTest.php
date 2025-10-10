<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Tests\Unit\DTOs;

use PHPUnit\Framework\TestCase;
use Shaykhnazar\HikvisionIsapi\DTOs\Face;

class FaceTest extends TestCase
{
    public function test_can_create_face_dto(): void
    {
        $faceData = base64_encode('fake_image_data');

        $face = new Face(
            employeeNo: 'EMP001',
            faceData: $faceData,
            faceLibId: 1,
            faceLibType: 'blackFD'
        );

        $this->assertSame('EMP001', $face->employeeNo);
        $this->assertSame($faceData, $face->faceData);
        $this->assertSame(1, $face->faceLibId);
        $this->assertSame('blackFD', $face->faceLibType);
    }

    public function test_can_convert_face_to_array(): void
    {
        $faceData = base64_encode('fake_image_data');

        $face = new Face(
            employeeNo: 'EMP001',
            faceData: $faceData,
            faceLibId: 1
        );

        $array = $face->toArray();

        $this->assertArrayHasKey('faceInfo', $array);
        $this->assertArrayHasKey('faceData', $array);
        $this->assertSame('EMP001', $array['faceInfo']['employeeNo']);
        $this->assertSame($faceData, $array['faceData']);
        $this->assertSame('blackFD', $array['faceInfo']['faceLibType']);
    }

    public function test_can_create_face_from_array(): void
    {
        $faceData = base64_encode('fake_image_data');

        $data = [
            'faceInfo' => [
                'employeeNo' => 'EMP001',
                'faceLibId' => 1,
                'faceLibType' => 'blackFD',
            ],
            'faceData' => $faceData,
        ];

        $face = Face::fromArray($data);

        $this->assertInstanceOf(Face::class, $face);
        $this->assertSame('EMP001', $face->employeeNo);
        $this->assertSame($faceData, $face->faceData);
        $this->assertSame(1, $face->faceLibId);
    }

    public function test_face_has_default_values(): void
    {
        $face = new Face(
            employeeNo: 'EMP001',
            faceData: base64_encode('fake_image_data')
        );

        $this->assertSame(1, $face->faceLibId);
        $this->assertSame('blackFD', $face->faceLibType);
    }

    public function test_face_is_readonly(): void
    {
        $face = new Face(
            employeeNo: 'EMP001',
            faceData: base64_encode('fake_image_data')
        );

        $reflection = new \ReflectionClass($face);
        $this->assertTrue($reflection->isReadOnly());
    }
}
