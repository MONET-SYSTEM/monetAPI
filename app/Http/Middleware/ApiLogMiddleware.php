<?php

namespace App\Http\Middleware;

use App\Models\ApiLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class ApiLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip logging for non-API routes
        if (!Str::startsWith($request->path(), 'api')) {
            return $next($request);
        }
        
        // Generate a unique ID for this request
        $requestId = (string) Str::uuid();
        
        // Start timer
        $startTime = microtime(true);
        
        // Execute the request
        $response = $next($request);
        
        // End timer
        $endTime = microtime(true);
        $duration = $endTime - $startTime;
        
        // Determine request status
        $status = $this->getStatusFromResponseCode($response->getStatusCode());
        
        // Log the API call
        $this->logApiCall(
            $requestId,
            $request->method(),
            $request->path(),
            $request->ip(),
            $request->userAgent() ?? 'Unknown',
            $this->sanitizeData($request->all()),
            $response->getStatusCode(),
            $this->getResponseBody($response),
            Auth::check() ? Auth::id() : null,
            $duration,
            $status
        );
        
        // Add request ID to response headers
        $response->header('X-Request-ID', $requestId);
        
        return $response;
    }
    
    /**
     * Log API call to database
     *
     * @param string $requestId
     * @param string $method
     * @param string $url
     * @param string $ipAddress
     * @param string $userAgent
     * @param array $requestPayload
     * @param int $responseCode
     * @param array $responseBody
     * @param int|null $userId
     * @param float $duration
     * @param string $status
     */
    private function logApiCall(
        string $requestId,
        string $method,
        string $url,
        string $ipAddress,
        string $userAgent,
        array $requestPayload,
        int $responseCode,
        array $responseBody,
        ?int $userId,
        float $duration,
        string $status
    ) {
        try {
            ApiLog::create([
                'request_id' => $requestId,
                'method' => $method,
                'url' => $url,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'request_payload' => $requestPayload,
                'response_code' => $responseCode,
                'response_body' => $responseBody,
                'user_id' => $userId,
                'duration' => $duration,
                'status' => $status
            ]);
        } catch (\Exception $e) {
            // Log error but don't break the application flow
            \Illuminate\Support\Facades\Log::error('Failed to create API log: ' . $e->getMessage());
        }
    }
    
    /**
     * Get response status from HTTP code
     *
     * @param int $code
     * @return string
     */
    private function getStatusFromResponseCode(int $code): string
    {
        if ($code >= 200 && $code < 300) {
            return ApiLog::STATUS_SUCCESS;
        } elseif ($code >= 400 && $code < 500) {
            return ApiLog::STATUS_WARNING;
        } else {
            return ApiLog::STATUS_ERROR;
        }
    }
    
    /**
     * Extract response body from response
     *
     * @param Response $response
     * @return array
     */
    private function getResponseBody(Response $response): array
    {
        $content = $response->getContent();
        
        if (empty($content)) {
            return [];
        }
        
        // Try to decode JSON
        $decoded = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            return $this->sanitizeData($decoded);
        }
        
        // If not JSON, return limited content
        return [
            'raw_content' => mb_substr($content, 0, 500) . (mb_strlen($content) > 500 ? '...' : '')
        ];
    }
    
    /**
     * Sanitize sensitive data
     *
     * @param array $data
     * @return array
     */
    private function sanitizeData(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'key', 'secret', 'api_key', 'auth_token', 'credentials'];
        
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                $data[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeData($value);
            }
        }
        
        return $data;
    }
}
