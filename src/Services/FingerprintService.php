<?php

declare(strict_types=1);

namespace Shaykhnazar\HikvisionIsapi\Services;

use Shaykhnazar\HikvisionIsapi\Client\HikvisionClient;

class FingerprintService
{
    private const ENDPOINT_CAPABILITIES = '/ISAPI/AccessControl/FingerPrint/capabilities';
    private const ENDPOINT_SEARCH = '/ISAPI/AccessControl/FingerPrint/Search';
    private const ENDPOINT_RECORD = '/ISAPI/AccessControl/FingerPrint/Record';
    private const ENDPOINT_MODIFY = '/ISAPI/AccessControl/FingerPrint/Modify';
    private const ENDPOINT_DELETE = '/ISAPI/AccessControl/FingerPrint/Delete';
    private const ENDPOINT_CAPTURE = '/ISAPI/AccessControl/CaptureFingerPrint';

    public function __construct(
        private readonly HikvisionClient $client
    ) {}

    public function getCapabilities(): array
    {
        return $this->client->get(self::ENDPOINT_CAPABILITIES);
    }

    public function search(
        int $page = 0,
        int $maxResults = 30,
        ?string $employeeNo = null
    ): array {
        $data = [
            'FingerPrintCond' => [
                'searchID' => (string) time(),
                'searchResultPosition' => $page * $maxResults,
                'maxResults' => $maxResults,
            ],
        ];

        if ($employeeNo) {
            $data['FingerPrintCond']['employeeNo'] = $employeeNo;
        }

        return $this->client->post(self::ENDPOINT_SEARCH, $data);
    }

    public function add(
        string $employeeNo,
        int $fingerPrintId,
        string $fingerPrintData
    ): array {
        $data = [
            'FingerPrint' => [
                'employeeNo' => $employeeNo,
                'fingerPrintID' => $fingerPrintId,
                'fingerData' => $fingerPrintData,
            ],
        ];

        return $this->client->post(self::ENDPOINT_RECORD, $data);
    }

    public function capture(int $timeout = 30): array
    {
        $data = [
            'FingerPrintCfg' => [
                'collectTimeout' => $timeout,
            ],
        ];

        return $this->client->post(self::ENDPOINT_CAPTURE, $data);
    }

    public function delete(array $employeeNos): array
    {
        $data = [
            'FingerPrintDelete' => [
                'mode' => 'byEmployeeNo',
                'EmployeeNoList' => array_map(
                    fn($no) => ['employeeNo' => $no],
                    $employeeNos
                ),
            ],
        ];

        return $this->client->put(self::ENDPOINT_DELETE, $data);
    }
}
