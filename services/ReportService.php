<?php
/**
 * RUMS — ReportService (API-backed)
 *
 * Maps to GET /api/v1/reports/* endpoints.
 * Return shapes are identical to the old DB-backed version.
 */
class ReportService
{
    private ApiClient $api;

    public function __construct()
    {
        $this->api = new ApiClient();
    }

    // ── Financial Report ──────────────────────────────────────

    /**
     * Returns ['summary' => [...], 'income' => [...], 'expenses' => [...], 'net' => float]
     */
    public function financial(string $dateFrom, string $dateTo, ?int $propertyId = null): array
    {
        $query = array_filter([
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'property_id' => $propertyId,
        ], fn($v) => $v !== null && $v !== '');

        $res = $this->api->get('reports/financial', $query);

        if (empty($res['success'])) {
            return ['summary' => [], 'income' => [], 'expenses' => [], 'net' => 0.0];
        }

        return $res['data'] ?? [];
    }

    // ── Occupancy Report ──────────────────────────────────────

    /**
     * Returns ['totals' => [...], 'by_property' => [...], 'by_type' => [...], 'trend' => [...]]
     */
    public function occupancy(?int $propertyId = null): array
    {
        $query = [];
        if ($propertyId) $query['property_id'] = $propertyId;

        $res = $this->api->get('reports/occupancy', $query);

        if (empty($res['success'])) {
            return ['totals' => [], 'by_property' => [], 'by_type' => [], 'trend' => []];
        }

        return $res['data'] ?? [];
    }

    // ── Maintenance Report ────────────────────────────────────

    /**
     * Returns ['summary' => [...], 'by_category' => [...], 'by_property' => [...]]
     */
    public function maintenance(string $dateFrom, string $dateTo, ?int $propertyId = null): array
    {
        $query = array_filter([
            'date_from'   => $dateFrom,
            'date_to'     => $dateTo,
            'property_id' => $propertyId,
        ], fn($v) => $v !== null && $v !== '');

        $res = $this->api->get('reports/maintenance', $query);

        if (empty($res['success'])) {
            return ['summary' => [], 'by_category' => [], 'by_property' => []];
        }

        return $res['data'] ?? [];
    }

    // ── Rent Collection Report ────────────────────────────────

    /**
     * Returns ['period', 'expected', 'collected', 'outstanding', 'collection_rate', 'rows']
     */
    public function rentCollection(int $year, int $month, ?int $propertyId = null): array
    {
        $query = array_filter([
            'year'        => $year,
            'month'       => $month,
            'property_id' => $propertyId,
        ], fn($v) => $v !== null);

        $res = $this->api->get('reports/rent-collection', $query);

        if (empty($res['success'])) {
            return [
                'period'          => "$year-" . str_pad($month, 2, '0', STR_PAD_LEFT),
                'expected'        => 0,
                'collected'       => 0,
                'outstanding'     => 0,
                'collection_rate' => 0,
                'rows'            => [],
            ];
        }

        return $res['data'] ?? [];
    }

    // ── Dashboard summary ─────────────────────────────────────

    /**
     * Returns aggregated dashboard KPIs from GET /api/v1/reports/dashboard.
     */
    public function dashboard(): array
    {
        $res = $this->api->get('reports/dashboard');
        return ($res['success'] ?? false) ? ($res['data'] ?? []) : [];
    }
}
