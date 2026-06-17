<?php
/**
 * RUMS — Units for a property (JSON)
 * Used by dynamic dropdowns. Delegates to GET /api/v1/properties/{id}/units.
 */
require_once __DIR__ . '/../config/config.php';
require_login();

header('Content-Type: application/json');

$property_id = int_param('property_id', 0, 'GET');
if (!$property_id) {
    echo json_encode([]);
    exit;
}

$res = (new ApiClient())->get("properties/$property_id/units");
echo json_encode($res['success'] ?? false ? $res['data'] : []);
