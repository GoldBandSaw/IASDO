<?php
declare(strict_types=1);

namespace CampusFlow\Tests\Harness;

use CURLFile;
use RuntimeException;

class HttpResponse {
    public function __construct(
        public int $status,
        public array $headers,
        public string $body
    ) {}

    public function json(): mixed {
        $decoded = json_decode($this->body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Failed to decode JSON response (error: " . json_last_error_msg() . "). Body: " . substr($this->body, 0, 300));
        }
        return $decoded;
    }
}

class HttpClient {
    private string $baseUrl;
    private ?string $cookieFile;
    private array $defaultHeaders = [];

    public function __construct(string $baseUrl = 'http://localhost:8000') {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->cookieFile = tempnam(sys_get_temp_dir(), 'cf_test_cookie_');
    }

    public function __destruct() {
        if ($this->cookieFile && file_exists($this->cookieFile)) {
            @unlink($this->cookieFile);
        }
    }

    public function setDefaultHeader(string $name, string $value): void {
        $this->defaultHeaders[$name] = $value;
    }

    public function request(string $method, string $path, array|string|null $data = null, array $headers = []): HttpResponse {
        $url = str_starts_with($path, 'http') ? $path : $this->baseUrl . '/' . ltrim($path, '/');
        $ch = curl_init($url);

        $mergedHeaders = array_merge($this->defaultHeaders, $headers);
        $headerList = [];
        $isJson = false;

        foreach ($mergedHeaders as $k => $v) {
            if (is_int($k)) {
                $headerList[] = $v;
                if (stripos($v, 'Content-Type: application/json') !== false) $isJson = true;
            } else {
                $headerList[] = "$k: $v";
                if (strcasecmp($k, 'Content-Type') === 0 && stripos($v, 'application/json') !== false) {
                    $isJson = true;
                }
            }
        }

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);

        $upperMethod = strtoupper($method);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $upperMethod);

        if ($data !== null) {
            if ($isJson && (is_array($data) || is_object($data))) {
                $payload = json_encode($data);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            } elseif (is_array($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, (string)$data);
            }
        }

        if (!empty($headerList)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headerList);
        }

        $rawResponse = curl_exec($ch);
        if ($rawResponse === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("cURL error connecting to [{$url}]: {$err}");
        }

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $rawHeaders = substr((string)$rawResponse, 0, $headerSize);
        $body = substr((string)$rawResponse, $headerSize);

        $parsedHeaders = [];
        foreach (explode("\r\n", $rawHeaders) as $line) {
            if (str_contains($line, ':')) {
                [$hk, $hv] = explode(':', $line, 2);
                $parsedHeaders[strtolower(trim($hk))] = trim($hv);
            }
        }

        return new HttpResponse($status, $parsedHeaders, $body);
    }

    public function get(string $path, array $headers = []): HttpResponse {
        return $this->request('GET', $path, null, $headers);
    }

    public function post(string $path, array|string|null $data = null, array $headers = []): HttpResponse {
        return $this->request('POST', $path, $data, $headers);
    }

    public function postJson(string $path, array $data, array $headers = []): HttpResponse {
        $headers['Content-Type'] = 'application/json';
        return $this->request('POST', $path, $data, $headers);
    }

    public function putJson(string $path, array $data, array $headers = []): HttpResponse {
        $headers['Content-Type'] = 'application/json';
        return $this->request('PUT', $path, $data, $headers);
    }

    public function delete(string $path, array $headers = []): HttpResponse {
        return $this->request('DELETE', $path, null, $headers);
    }

    public function postFile(string $path, string $fieldName, string $filePath, array $extraFields = [], array $headers = []): HttpResponse {
        $postData = $extraFields;
        $mime = mime_content_type($filePath) ?: 'application/octet-stream';
        $postData[$fieldName] = new CURLFile($filePath, $mime, basename($filePath));
        return $this->request('POST', $path, $postData, $headers);
    }

    public function login(string $username, string $password): HttpResponse {
        return $this->postJson('/api/auth/login', [
            'username' => $username,
            'password' => $password
        ]);
    }
}
