<?php
/**
 * RUMS — MaintenanceService (API-backed)
 */
class MaintenanceService
{
    private ApiClient $api;

    public function __construct()
    {
        $this->api = new ApiClient();
    }

    // ── List ──────────────────────────────────────────────────

    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = $this->buildQuery($filters, [
            'status', 'priority', 'property_id', 'unit_id', 'assigned_to', 'tenant_id',
        ], ['page' => $page, 'per_page' => $perPage]);

        $res = $this->api->get('maintenance', $query);
        return $this->paginatedResult($res, $perPage);
    }

    // ── Get one ───────────────────────────────────────────────

    public function find(int $id): ?array
    {
        $res = $this->api->get("maintenance/$id");
        return ($res['success'] ?? false) ? ($res['data'] ?? null) : null;
    }

    // ── Create ────────────────────────────────────────────────

    public function create(array $data): array
    {
        $res = $this->api->post('maintenance', $data);
        return [
            'success'        => $res['success']                   ?? false,
            'message'        => $res['message']                   ?? ($res['success'] ? 'Work order created.' : 'Request failed.'),
            'id'             => $res['data']['id']                ?? null,
            'request_number' => $res['data']['request_number']    ?? null,
            'errors'         => $res['errors']                    ?? [],
        ];
    }

    // ── Update ────────────────────────────────────────────────

    public function update(int $id, array $data): array
    {
        $res = $this->api->put("maintenance/$id", $data);
        return $this->mutationResult($res);
    }

    // ── Summary ───────────────────────────────────────────────

    /**
     * Returns aggregate counts/costs for maintenance requests.
     * Shape: ['total', 'open', 'in_progress', 'completed', 'urgent', 'high', 'total_cost', 'avg_days_to_resolve']
     */
    public function summary(?int $propertyId = null): array
    {
        $query = [];
        if ($propertyId) $query['property_id'] = $propertyId;

        $res = $this->api->get('maintenance/summary', $query);

        if (empty($res['success'])) {
            return ['total' => 0, 'open' => 0, 'in_progress' => 0, 'completed' => 0,
                    'urgent' => 0, 'high' => 0, 'total_cost' => 0, 'avg_days_to_resolve' => null];
        }

        return $res['data'] ?? [];
    }

    // ── Helpers ───────────────────────────────────────────────

    private function buildQuery(array $filters, array $keys, array $extra = []): array
    {
        $query = $extra;
        foreach ($keys as $k) {
            if (isset($filters[$k]) && $filters[$k] !== '' && $filters[$k] !== null) {
                $query[$k] = $filters[$k];
            }
        }
        return $query;
    }

    private function paginatedResult(array $res, int $perPage): array
    {
        if (empty($res['success'])) {
            return [
                'data' => [],
                'meta' => ['total' => 0, 'per_page' => $perPage, 'current_page' => 1, 'total_pages' => 1],
            ];
        }
        return ['data' => $res['data'] ?? [], 'meta' => $res['meta'] ?? []];
    }

    private function mutationResult(array $res): array
    {
        return [
            'success' => $res['success'] ?? false,
            'message' => $res['message'] ?? ($res['success'] ? 'Done.' : 'Request failed.'),
            'id'      => $res['data']['id'] ?? null,
            'errors'  => $res['errors']     ?? [],
        ];
    }
}
