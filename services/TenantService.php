<?php
/**
 * RUMS — TenantService (API-backed)
 */
class TenantService
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
            'search', 'status', 'property_id',
        ], ['page' => $page, 'per_page' => $perPage]);

        $res = $this->api->get('tenants', $query);
        return $this->paginatedResult($res, $perPage);
    }

    // ── Get one ───────────────────────────────────────────────

    public function find(int $id): ?array
    {
        $res = $this->api->get("tenants/$id");
        return ($res['success'] ?? false) ? ($res['data'] ?? null) : null;
    }

    // ── Create ────────────────────────────────────────────────

    public function create(array $data): array
    {
        $res = $this->api->post('tenants', $data);
        return $this->mutationResult($res);
    }

    // ── Update ────────────────────────────────────────────────

    public function update(int $id, array $data): array
    {
        $res = $this->api->put("tenants/$id", $data);
        return $this->mutationResult($res);
    }

    // ── Tenant statement ──────────────────────────────────────

    /**
     * Returns ['payments' => [], 'invoices' => [], 'total_billed' => 0, 'total_paid' => 0, 'outstanding' => 0]
     */
    public function getStatement(int $tenantId, string $dateFrom, string $dateTo): array
    {
        $res = $this->api->get("tenants/$tenantId/statement", [
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
        ]);

        if (empty($res['success'])) {
            return ['payments' => [], 'invoices' => [], 'total_billed' => 0, 'total_paid' => 0, 'outstanding' => 0];
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
