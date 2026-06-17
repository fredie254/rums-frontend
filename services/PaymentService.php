<?php
/**
 * RUMS — PaymentService (API-backed)
 */
class PaymentService
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
            'tenant_id', 'lease_id', 'invoice_id', 'method',
            'date_from', 'date_to', 'property_id',
        ], ['page' => $page, 'per_page' => $perPage]);

        $res = $this->api->get('payments', $query);
        return $this->paginatedResult($res, $perPage);
    }

    // ── Get one ───────────────────────────────────────────────

    public function find(int $id): ?array
    {
        $res = $this->api->get("payments/$id");
        return ($res['success'] ?? false) ? ($res['data'] ?? null) : null;
    }

    // ── Record payment ────────────────────────────────────────

    public function record(array $data): array
    {
        $res = $this->api->post('payments', $data);
        return [
            'success'     => $res['success']              ?? false,
            'message'     => $res['message']              ?? ($res['success'] ? 'Payment recorded.' : 'Request failed.'),
            'id'          => $res['data']['id']           ?? null,
            'payment_ref' => $res['data']['payment_ref']  ?? null,
            'errors'      => $res['errors']               ?? [],
        ];
    }

    // ── Summary ───────────────────────────────────────────────

    /**
     * Returns aggregated totals for a date range (and optional property).
     * Shape: ['count', 'total', 'mpesa_total', 'bank_total', 'cash_total']
     */
    public function summary(string $dateFrom, string $dateTo, ?int $propertyId = null): array
    {
        $query = array_filter([
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'property_id' => $propertyId,
        ], fn($v) => $v !== null && $v !== '');

        $res = $this->api->get('payments/summary', $query);

        if (empty($res['success'])) {
            return ['count' => 0, 'total' => 0, 'mpesa_total' => 0, 'bank_total' => 0, 'cash_total' => 0];
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
}
