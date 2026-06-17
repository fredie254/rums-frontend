<?php
/**
 * RUMS — PropertyService (API-backed)
 *
 * All data operations go through the REST API.
 * Return shapes are identical to the old DB-backed version
 * so existing page code requires no changes.
 */
class PropertyService
{
    private ApiClient $api;

    public function __construct()
    {
        $this->api = new ApiClient();
    }

    // ── List ──────────────────────────────────────────────────

    /**
     * Returns ['data' => [...], 'meta' => [total, per_page, current_page, total_pages, ...]]
     */
    public function list(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $query = $this->buildQuery($filters, [
            'search', 'status', 'landlord_id',
        ], ['page' => $page, 'per_page' => $perPage]);

        $res = $this->api->get('properties', $query);

        return $this->paginatedResult($res, $perPage);
    }

    // ── Get one ───────────────────────────────────────────────

    public function find(int $id): ?array
    {
        $res = $this->api->get("properties/$id");
        return ($res['success'] ?? false) ? ($res['data'] ?? null) : null;
    }

    // ── Stats (included in find(); exposed separately for backward compat) ──

    public function stats(int $propertyId): array
    {
        $prop = $this->find($propertyId);
        return $prop['stats'] ?? [];
    }

    // ── Create ────────────────────────────────────────────────

    public function create(array $data): array
    {
        $res = $this->api->post('properties', $data);
        return $this->mutationResult($res);
    }

    // ── Update ────────────────────────────────────────────────

    public function update(int $id, array $data): array
    {
        $res = $this->api->put("properties/$id", $data);
        return $this->mutationResult($res);
    }

    // ── Delete ────────────────────────────────────────────────

    public function delete(int $id): array
    {
        $res = $this->api->delete("properties/$id");
        return $this->mutationResult($res);
    }

    // ── Helpers ───────────────────────────────────────────────

    /** Build query array from filters, keeping only present non-empty values. */
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

    /** Normalise a paginated API response into ['data'=>[], 'meta'=>[]]. */
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

    /** Normalise a create/update/delete API response. */
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
