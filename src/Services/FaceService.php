<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Services;

use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;

class FaceService
{
    private const ENDPOINT_FDLIB = '/ISAPI/Intelligent/FDLib';
    private const ENDPOINT_CAPABILITIES = '/ISAPI/Intelligent/FDLib/capabilities';

    public function __construct(
        private readonly HikvisionClient $client
    ) {}

    public function getLibraries(): array
    {
        return $this->client->get(self::ENDPOINT_FDLIB);
    }

    public function createLibrary(array $data): array
    {
        return $this->client->post(self::ENDPOINT_FDLIB, $data);
    }

    public function getCapabilities(): array
    {
        return $this->client->get(self::ENDPOINT_CAPABILITIES);
    }

    public function uploadFace(string $employeeNo, string $faceImageBase64, int $fdid = 1): array
    {
        $endpoint = "/ISAPI/Intelligent/FDLib/{$fdid}/picture";

        $data = [
            'faceInfo' => [
                'employeeNo' => $employeeNo,
                'faceLibType' => 'blackFD',
            ],
            'faceData' => $faceImageBase64,
        ];

        return $this->client->post($endpoint, $data);
    }

    public function deleteFace(int $fdid, int $fpid): array
    {
        $endpoint = "/ISAPI/Intelligent/FDLib/{$fdid}/picture/{$fpid}";
        return $this->client->delete($endpoint);
    }
}
