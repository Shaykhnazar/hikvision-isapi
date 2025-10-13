<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Services;

use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;

class FaceService
{
    private const ENDPOINT_FDLIB = '/ISAPI/Intelligent/FDLib';
    private const ENDPOINT_CAPABILITIES = '/ISAPI/Intelligent/FDLib/capabilities';
    private const ENDPOINT_FACE_SEARCH = '/ISAPI/Intelligent/FDLib/FDSearch';
    private const ENDPOINT_FACE_DATA_RECORD = '/ISAPI/Intelligent/FDLib/FaceDataRecord';

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

    /**
     * Search for face data
     *
     * @param int $page Page number (searchResultPosition = page * maxResults)
     * @param int $maxResults Maximum results per page (1-30)
     * @param string $faceLibType Face library type (e.g., 'blackFD')
     * @param int|null $fdid Face library ID
     * @param string|null $fpid Face picture ID
     * @return array Search results
     */
    public function searchFace(
        int $page = 0,
        int $maxResults = 30,
        string $faceLibType = 'blackFD',
        ?int $fdid = null,
        ?string $fpid = null
    ): array {
        $data = [
            'searchResultPosition' => $page * $maxResults,
            'maxResults' => $maxResults,
            'faceLibType' => $faceLibType,
        ];

        if ($fdid !== null) {
            $data['FDID'] = (string) $fdid;
        }

        if ($fpid !== null) {
            $data['FPID'] = $fpid;
        }

        return $this->client->post(self::ENDPOINT_FACE_SEARCH, $data);
    }

    /**
     * Delete face search data
     *
     * @param int $fdid Face library ID
     * @param string $faceLibType Face library type (e.g., 'blackFD')
     * @return array Deletion result
     */
    public function deleteFaceSearch(int $fdid, string $faceLibType = 'blackFD'): array
    {
        $queryParams = [
            'FDID' => $fdid,
            'faceLibType' => $faceLibType,
        ];

        return $this->client->put(self::ENDPOINT_FACE_SEARCH . '/Delete', [], $queryParams);
    }

    /**
     * Upload face data record with image file
     *
     * @param int $fdid Face library ID
     * @param string $fpid Face picture ID
     * @param string $imageContent Binary image content (JPEG format)
     * @param string $faceLibType Face library type (e.g., 'blackFD')
     * @return array Upload result with FPID
     */
    public function uploadFaceDataRecord(
        int $fdid,
        string $fpid,
        string $imageContent,
        string $faceLibType = 'blackFD'
    ): array {
        $faceDataRecord = json_encode([
            'faceLibType' => $faceLibType,
            'FDID' => (string) $fdid,
            'FPID' => $fpid,
        ]);

        $multipart = [
            [
                'name' => 'FaceDataRecord',
                'contents' => $faceDataRecord,
            ],
            [
                'name' => 'FaceImage',
                'contents' => $imageContent,
                'filename' => 'face.jpg',
                'headers' => [
                    'Content-Type' => 'image/jpeg',
                ],
            ],
        ];

        return $this->client->postMultipart(self::ENDPOINT_FACE_DATA_RECORD, $multipart);
    }
}
