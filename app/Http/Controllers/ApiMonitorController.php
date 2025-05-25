<?php

namespace App\Http\Controllers;

use App\Models\ApiLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class ApiMonitorController extends Controller
{
    /**
     * Display the API monitoring dashboard
     *
     * @param Request $request
     * @return \Illuminate\View\View
     */
    public function index(Request $request)
    {
        // Get API statistics
        $stats = $this->getApiStatistics($request);
        
        // Get recent logs
        $logs = ApiLog::orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
        
        return view('admin.api.monitor', compact('stats', 'logs'));
    }
    
    /**
     * Get API statistics
     *
     * @param Request $request
     * @return array
     */
    private function getApiStatistics(Request $request)
    {
        // Time period for statistics
        $period = $request->input('period', 'day');
        
        switch ($period) {
            case 'week':
                $startDate = now()->subWeek();
                break;
            case 'month':
                $startDate = now()->subMonth();
                break;
            case 'year':
                $startDate = now()->subYear();
                break;
            default:
                $startDate = now()->subDay();
        }
        
        // Total requests
        $totalRequests = ApiLog::where('created_at', '>=', $startDate)->count();
        
        // Successful requests
        $successfulRequests = ApiLog::where('created_at', '>=', $startDate)
            ->where('status', 'success')
            ->count();
        
        // Failed requests
        $failedRequests = ApiLog::where('created_at', '>=', $startDate)
            ->where('status', 'error')
            ->count();
        
        // Average response time
        $avgResponseTime = ApiLog::where('created_at', '>=', $startDate)
            ->avg('duration') ?: 0;
        
        // Requests by endpoint
        $endpointStats = ApiLog::where('created_at', '>=', $startDate)
            ->select('url', 'method', DB::raw('count(*) as count'))
            ->groupBy('url', 'method')
            ->orderBy('count', 'desc')
            ->limit(10)
            ->get();
        
        // Status code distribution
        $statusCodes = ApiLog::where('created_at', '>=', $startDate)
            ->select('response_code', DB::raw('count(*) as count'))
            ->groupBy('response_code')
            ->orderBy('count', 'desc')
            ->get()
            ->pluck('count', 'response_code')
            ->toArray();
        
        // Error rates over time
        $errorRates = ApiLog::where('created_at', '>=', $startDate)
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m-%d %H:00:00") as hour'),
                DB::raw('count(*) as total'),
                DB::raw('SUM(CASE WHEN status = "error" THEN 1 ELSE 0 END) as errors')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get()
            ->map(function ($item) {
                $item->error_rate = $item->total > 0 ? round(($item->errors / $item->total) * 100, 2) : 0;
                return $item;
            });
        
        return [
            'total_requests' => $totalRequests,
            'successful_requests' => $successfulRequests,
            'failed_requests' => $failedRequests,
            'average_response_time' => round($avgResponseTime * 1000, 2), // Convert to ms
            'endpoints' => $endpointStats,
            'status_codes' => $statusCodes,
            'error_rates' => $errorRates,
            'period' => $period
        ];
    }
    
    /**
     * Show API log details
     *
     * @param string $requestId
     * @return \Illuminate\View\View
     */
    public function showLog($requestId)
    {
        $log = ApiLog::where('request_id', $requestId)->firstOrFail();
        
        return view('admin.api.log-details', compact('log'));
    }
    
    /**
     * Export API logs as CSV
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function exportLogs(Request $request)
    {
        $startDate = $request->input('start_date', now()->subDays(30)->format('Y-m-d'));
        $endDate = $request->input('end_date', now()->format('Y-m-d'));
        $status = $request->input('status');
        
        $query = ApiLog::whereBetween('created_at', [$startDate . ' 00:00:00', $endDate . ' 23:59:59']);
        
        if ($status) {
            $query->where('status', $status);
        }
        
        $logs = $query->orderBy('created_at', 'desc')->get();
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="api_logs_' . date('Y-m-d') . '.csv"',
        ];
        
        $callback = function () use ($logs) {
            $file = fopen('php://output', 'w');
            
            // Add headers
            fputcsv($file, [
                'Request ID',
                'Method',
                'URL',
                'IP Address',
                'Status Code',
                'Duration (ms)',
                'Status',
                'User ID',
                'Timestamp'
            ]);
            
            // Add data
            foreach ($logs as $log) {
                fputcsv($file, [
                    $log->request_id,
                    $log->method,
                    $log->url,
                    $log->ip_address,
                    $log->response_code,
                    $log->duration * 1000, // Convert to ms
                    $log->status,
                    $log->user_id,
                    $log->created_at
                ]);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, $headers);
    }
}
