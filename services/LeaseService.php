<?php
/**
 * RUMS — LeaseService (API-backed)
 */
class LeaseService
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
            'status', 'property_id', 'tenant_id', 'unit_id', 'expiring_days',
        ], ['page' => $page, 'per_page' => $perPage]);

        $res = $this->api->get('leases', $query);
        return $this->paginatedResult($res, $perPage);
    }

    // ── Get one ───────────────────────────────────────────────

    public function find(int $id): ?array
    {
        $res = $this->api->get("leases/$id");
        return ($res['success'] ?? false) ? ($res['data'] ?? null) : null;
    }

    // ── Create ────────────────────────────────────────────────

    public function create(array $data): array
    {
        $res = $this->api->post('leases', $data);
        return $this->mutationResult($res);
    }

    // ── Terminate ─────────────────────────────────────────────

    public function terminate(int $id, string $reason = ''): array
    {
        $res = $this->api->post("leases/$id/terminate", ['reason' => $reason]);
        return $this->mutationResult($res);
    }

    // ── Expiring leases ───────────────────────────────────────

    /**
     * Returns a flat array of leases expiring within $days days.
     */
    public function getExpiring(int $days = 30): array
    {
        $res = $this->api->get('leases', ['expiring_days' => $days, 'per_page' => 200]);

        if (empty($res['success'])) return [];
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
            'success'      => $res['success']       ?? false,
            'message'      => $res['message']        ?? ($res['success'] ? 'Done.' : 'Request failed.'),
            'id'           => $res['data']['id']     ?? null,
            'lease_number' => $res['data']['lease_number'] ?? null,
            'errors'       => $res['errors']         ?? [],
        ];
    }
}
