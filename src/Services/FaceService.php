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
     * @param  int  $page  Page number (searchResultPosition = page * maxResults)
     * @param  int  $maxResults  Maximum results per page (1-30)
     * @param  string  $faceLibType  Face library type (e.g., 'blackFD')
     * @param  int|null  $fdid  Face library ID
     * @param  string|null  $fpid  Face picture ID
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
     * @param  int  $fdid  Face library ID
     * @param  string  $faceLibType  Face library type (e.g., 'blackFD')
     * @return array Deletion result
     */
    public function deleteFaceSearch(int $fdid, string $faceLibType = 'blackFD'): array
    {
        $queryParams = [
            'FDID' => $fdid,
            'faceLibType' => $faceLibType,
        ];

        return $this->client->put(self::ENDPOINT_FACE_SEARCH.'/Delete', [], $queryParams);
    }

    /**
     * Upload face data record with image file
     * Official Hikvision ISAPI endpoint for adding face pictures
     *
     * @param  int  $fdid  Face library ID (1 for visible light library)
     * @param  string  $fpid  Face picture ID (should match employee number, max 63 bytes)
     * @param  string  $imageContent  Binary image content (JPEG format)
     * @param  string  $faceLibType  Face library type: 'blackFD' (list library), 'staticFD' (static library), 'infraredFD' (IR library)
     * @param  array  $additionalData  Optional additional fields (name, gender, bornTime, etc.)
     * @return array Upload result with FPID
     */
    public function uploadFaceDataRecord(
        int $fdid,
        string $fpid,
        string $imageContent,
        string $faceLibType = 'blackFD',
        array $additionalData = []
    ): array {
        // Build face data record JSON with required and optional fields
        $faceData = array_merge([
            'faceLibType' => $faceLibType,
            'FDID' => (string) $fdid,
            'FPID' => $fpid,
        ], $additionalData);

        $faceDataRecord = json_encode($faceData);

        $multipart = [
            [
                'name' => 'FaceDataRecord',
                'contents' => $faceDataRecord,
                // No Content-Type header - send as plain text like Postman
            ],
            [
                'name' => 'img',
                'contents' => $imageContent,
                'filename' => "{$fpid}.jpg", // Use unique filename per employee
                'headers' => [
                    'Content-Type' => 'image/jpeg',
                ],
            ],
        ];

        return $this->client->postMultipart(self::ENDPOINT_FACE_DATA_RECORD, $multipart);
    }

    /**
     * Count total face records in all face picture libraries
     *
     * @return int Total number of face records
     */
    public function countAllFaceRecords(): int
    {
        $endpoint = '/ISAPI/Intelligent/FDLib/Count';
        $response = $this->client->get($endpoint);

        return $response['recordDataNumber'] ?? 0;
    }

    /**
     * Count face records in a specific face picture library
     *
     * @param  int  $fdid  Face library ID
     * @param  string  $faceLibType  Face library type ('blackFD', 'staticFD', 'infraredFD')
     * @param  string|null  $terminalNo  Optional terminal number
     * @return int Number of face records in the specified library
     */
    public function countFaceRecordsInLibrary(
        int $fdid,
        string $faceLibType = 'blackFD',
        ?string $terminalNo = null
    ): int {
        $queryParams = [
            'FDID' => $fdid,
            'faceLibType' => $faceLibType,
        ];

        if ($terminalNo !== null) {
            $queryParams['terminalNo'] = $terminalNo;
        }

        $endpoint = '/ISAPI/Intelligent/FDLib/Count?'.http_build_query($queryParams);
        $response = $this->client->get($endpoint);

        return $response['recordDataNumber'] ?? 0;
    }

    /**
     * Apply/Set up face picture data (links face picture to person information)
     * This endpoint should be called after adding face via uploadFaceDataRecord()
     * to properly link the face picture to the person record
     *
     * @param  int  $fdid  Face library ID
     * @param  string  $fpid  Face picture ID (matches employee number)
     * @param  string  $imageContent  Binary image content (JPEG format)
     * @param  string  $faceLibType  Face library type ('blackFD', 'staticFD', 'infraredFD')
     * @param  array  $additionalData  Optional additional fields
     * @return array Setup result
     */
    public function setupFaceData(
        int $fdid,
        string $fpid,
        string $imageContent,
        string $faceLibType = 'blackFD',
        array $additionalData = []
    ): array {
        $endpoint = '/ISAPI/Intelligent/FDLib/FDSetUp';

        // Build face data with required fields
        $faceData = array_merge([
            'faceLibType' => $faceLibType,
            'FDID' => (string) $fdid,
            'FPID' => $fpid,
        ], $additionalData);

        $faceDataJson = json_encode($faceData);

        $multipart = [
            [
                'name' => 'faceURL',
                'contents' => $faceDataJson,
                // No Content-Type header for JSON field
            ],
            [
                'name' => 'img',
                'contents' => $imageContent,
                'filename' => "{$fpid}.jpg", // Use unique filename per employee
                'headers' => [
                    'Content-Type' => 'image/jpeg',
                ],
            ],
        ];

        return $this->client->putMultipart($endpoint, $multipart);
    }

    /**
     * Edit/modify face records in the face picture library
     * Can update face image and/or metadata
     *
     * @param  int  $fdid  Face library ID
     * @param  string  $fpid  Face picture ID
     * @param  string  $imageContent  Binary image content (JPEG format)
     * @param  string  $faceLibType  Face library type ('blackFD', 'staticFD', 'infraredFD')
     * @param  array  $additionalData  Optional fields to update
     * @return array Modification result
     */
    public function modifyFaceRecord(
        int $fdid,
        string $fpid,
        string $imageContent,
        string $faceLibType = 'blackFD',
        array $additionalData = []
    ): array {
        $endpoint = '/ISAPI/Intelligent/FDLib/FDModify';

        $faceData = array_merge([
            'faceLibType' => $faceLibType,
            'FDID' => (string) $fdid,
            'FPID' => $fpid,
        ], $additionalData);

        $faceDataJson = json_encode($faceData);

        $multipart = [
            [
                'name' => 'faceURL',
                'contents' => $faceDataJson,
                // No Content-Type header for JSON field
            ],
            [
                'name' => 'img',
                'contents' => $imageContent,
                'filename' => "{$fpid}.jpg", // Use unique filename per employee
                'headers' => [
                    'Content-Type' => 'image/jpeg',
                ],
            ],
        ];

        return $this->client->putMultipart($endpoint, $multipart);
    }

    /**
     * Delete entire face picture library
     * WARNING: This deletes ALL face pictures in ALL libraries
     *
     * @return array Deletion result
     */
    public function deleteAllFaceLibraries(): array
    {
        return $this->client->delete('/ISAPI/Intelligent/FDLib');
    }
}
