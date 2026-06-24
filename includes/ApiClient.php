<?php
/**
 * RUMS — REST API Client
 *
 * Thin cURL wrapper that authenticates every request with the Bearer token
 * stored in the user's session. Instantiate once per service method call.
 *
 * Usage:
 *   $api = new ApiClient();
 *   $res = $api->get('properties', ['page' => 1, 'status' => 'active']);
 *   $res = $api->post('auth/login', ['email' => ..., 'password' => ...]);
 */
class ApiClient
{
    private string  $base;
    private ?string $token;
    private int     $timeout;

    /**
     * @param string|null $token   Override session token (use null to pass no token, e.g. for login).
     * @param int         $timeout cURL timeout in seconds.
     */
    public function __construct(?string $token = 'from_session', int $timeout = 15)
    {
        $this->base    = rtrim(API_URL, '/') . '/api/v1';
        $this->timeout = $timeout;

        if ($token === 'from_session') {
            $this->token = $_SESSION['api_token'] ?? null;
        } else {
            $this->token = $token; // null → unauthenticated, explicit string → override
        }
    }

    // ── HTTP verbs ────────────────────────────────────────────

    public function get(string $path, array $query = []): array
    {
        return $this->request('GET', $path, [], $query);
    }

    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, $data);
    }

    public function put(string $path, array $data = []): array
    {
        return $this->request('PUT', $path, $data);
    }

    public function patch(string $path, array $data = []): array
    {
        return $this->request('PATCH', $path, $data);
    }

    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }

    /**
     * Upload a file via multipart/form-data.
     *
     * @param string $path       API path (e.g. 'documents/upload')
     * @param array  $fileField  One element from $_FILES, e.g. $_FILES['file']
     * @param array  $fields     Additional POST fields (title, document_type, etc.)
     */
    public function upload(string $path, array $fileField, array $fields = []): array
    {
        $url = $this->base . '/' . ltrim($path, '/');

        $postData = $fields;
        $postData['file'] = new CURLFile(
            $fileField['tmp_name'],
            $fileField['type'],
            $fileField['name']
        );

        $headers = ['Accept: application/json'];
        if ($this->token !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        // Do NOT set Content-Type — cURL sets it automatically for multipart

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 60, // allow time for large uploads
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_SSL_VERIFYPEER => defined('APP_ENV') && APP_ENV === 'production',
            CURLOPT_SSL_VERIFYHOST => defined('APP_ENV') && APP_ENV === 'production' ? 2 : 0,
        ]);

        $body   = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['success' => false, 'message' => 'Upload connection error: ' . $error];
        }
        if ($body === false || $body === '') {
            return ['success' => false, 'message' => 'Empty response from API.'];
        }

        $decoded = json_decode($body, true);
        return is_array($decoded) ? $decoded : ['success' => false, 'message' => 'Invalid API response.'];
    }

    // ── Core request ──────────────────────────────────────────

    private function request(
        string $method,
        string $path,
        array  $data  = [],
        array  $query = []
    ): array {
        $url = $this->base . '/' . ltrim($path, '/');
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
        ];
        if ($this->token !== null) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_CUSTOMREQUEST  => $method,
            // Verify SSL only in production; skip in local/staging
            CURLOPT_SSL_VERIFYPEER => defined('APP_ENV') && APP_ENV === 'production',
            CURLOPT_SSL_VERIFYHOST => defined('APP_ENV') && APP_ENV === 'production' ? 2 : 0,
        ]);

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && $data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $body   = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("[ApiClient] cURL error on $method $url: $error");
            return ['success' => false, 'message' => 'Service temporarily unavailable.', 'code' => 0];
        }

        if ($body === false || $body === '') {
            error_log("[ApiClient] Empty response ($status) on $method $url");
            return ['success' => false, 'message' => 'Empty response from API.', 'code' => $status];
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            error_log("[ApiClient] Non-JSON response ($status) on $method $url: " . substr($body, 0, 200));
            return ['success' => false, 'message' => 'Invalid API response.', 'code' => $status];
        }

        // 401 — token expired or revoked: clear session and redirect to login
        if ($status === 401) {
            $_SESSION = [];
            if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
            if (!headers_sent()) {
                header('Location: ' . (defined('BASE_URL') ? BASE_URL . '/index' : '/'));
                exit;
            }
        }

        return $decoded;
    }
}
