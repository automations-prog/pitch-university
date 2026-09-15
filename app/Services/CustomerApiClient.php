<?php

namespace App\Services;

use Generator;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class CustomerApiClient
{
    private readonly ?string $domain;

    private readonly ?string $apiKey;

    private readonly ?string $locationId;

    private readonly int $pageSize;

    public function __construct(
        ?string $domain = null,
        ?string $apiKey = null,
        ?string $locationId = null,
        ?int $pageSize = null,
    ) {
        $this->domain = $domain ?? config('services.customer_api.domain');
        $this->apiKey = $apiKey ?? config('services.customer_api.key');
        $this->locationId = $locationId ?? config('services.customer_api.location_id');
        $this->pageSize = $pageSize ?? (int) config('services.customer_api.page_size', 50);
    }

    /**
     * Fetch a single page of the location's student roster and training progress.
     *
     * @return array<string, mixed>
     */
    public function fetchTrainingProgressPage(int $pageIndex): array
    {
        return $this->request()
            ->get("/services/CustomerApi.svc/locations/{$this->locationId}/trainingprogress", [
                'PageIndex' => $pageIndex,
                'PageSize' => $this->pageSize,
                'includeChildren' => 'false',
            ])
            ->throw()
            ->json() ?? [];
    }

    /**
     * Fetch the training programs for a single student.
     *
     * @return array<string, mixed>
     */
    public function fetchUserTrainingProgress(string $userId): array
    {
        return $this->request()
            ->get("/services/CustomerApi.svc/users/{$userId}/trainingprogress")
            ->throw()
            ->json() ?? [];
    }

    private function request(): PendingRequest
    {
        if (! $this->domain || ! $this->apiKey) {
            throw new RuntimeException('The Customer API domain and key must be configured.');
        }

        return Http::withHeaders(['X-OCD-ApiKey' => $this->apiKey])
            ->baseUrl($this->domain);
    }

    /**
     * Lazily page through the full student roster, one student at a time.
     *
     * @return Generator<int, array<string, mixed>>
     */
    public function eachStudent(): Generator
    {
        $pageIndex = 0;

        do {
            $page = $this->fetchTrainingProgressPage($pageIndex);
            $students = $page['students'] ?? [];

            foreach ($students as $student) {
                yield $student;
            }

            $pagesCount = (int) ($page['pagedResults']['pagesCount'] ?? 0);
            $pageIndex++;
        } while ($pageIndex < $pagesCount);
    }
}
