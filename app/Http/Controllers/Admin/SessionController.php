<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Carbon\Carbon;

class SessionController extends Controller
{
    /**
     * Display a listing of active sessions.
     */
    public function index(Request $request)
    {
        $query = DB::table('sessions')
            ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
            ->select([
                'sessions.id',
                'sessions.user_id',
                'sessions.ip_address',
                'sessions.user_agent',
                'sessions.last_activity',
                'users.name as user_name',
                'users.email as user_email'
            ]);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('users.name', 'LIKE', "%{$search}%")
                  ->orWhere('users.email', 'LIKE', "%{$search}%")
                  ->orWhere('sessions.ip_address', 'LIKE', "%{$search}%");
            });
        }

        if ($request->filled('user_type')) {
            if ($request->user_type === 'authenticated') {
                $query->whereNotNull('sessions.user_id');
            } else if ($request->user_type === 'guest') {
                $query->whereNull('sessions.user_id');
            }
        }

        if ($request->filled('activity_period')) {
            $period = $request->activity_period;
            $timestamp = match($period) {
                'last_hour' => Carbon::now()->subHour()->timestamp,
                'last_day' => Carbon::now()->subDay()->timestamp,
                'last_week' => Carbon::now()->subWeek()->timestamp,
                'last_month' => Carbon::now()->subMonth()->timestamp,
                default => 0
            };
            $query->where('sessions.last_activity', '>=', $timestamp);
        }

        // Order by last activity (most recent first)
        $sessions = $query->orderBy('sessions.last_activity', 'desc')
                         ->paginate(15)
                         ->appends($request->query());

        // Transform sessions data
        $sessions->getCollection()->transform(function ($session) {
            $session->last_activity_formatted = Carbon::createFromTimestamp($session->last_activity)->diffForHumans();
            $session->last_activity_full = Carbon::createFromTimestamp($session->last_activity)->format('Y-m-d H:i:s');
            $session->user_agent_parsed = $this->parseUserAgent($session->user_agent);
            $session->is_current = $session->id === Session::getId();
            return $session;
        });

        // Get statistics
        $stats = $this->getSessionStatistics();

        return view('admin.sessions.index', compact('sessions', 'stats'));
    }

    /**
     * Show session details.
     */
    public function show($sessionId)
    {
        $session = DB::table('sessions')
            ->leftJoin('users', 'sessions.user_id', '=', 'users.id')
            ->select([
                'sessions.*',
                'users.name as user_name',
                'users.email as user_email',
                'users.avatar as user_avatar'
            ])
            ->where('sessions.id', $sessionId)
            ->first();

        if (!$session) {
            return redirect()->route('admin.sessions.index')->with('error', 'Session not found.');
        }

        // Parse session data
        $session->last_activity_formatted = Carbon::createFromTimestamp($session->last_activity)->diffForHumans();
        $session->last_activity_full = Carbon::createFromTimestamp($session->last_activity)->format('Y-m-d H:i:s');
        $session->user_agent_parsed = $this->parseUserAgent($session->user_agent);
        $session->is_current = $session->id === Session::getId();
        $session->payload_decoded = $this->decodeSessionPayload($session->payload);

        return view('admin.sessions.show', compact('session'));
    }

    /**
     * Terminate a specific session.
     */
    public function destroy($sessionId)
    {
        if ($sessionId === Session::getId()) {
            return redirect()->back()->with('error', 'Cannot terminate your own session.');
        }

        $deleted = DB::table('sessions')->where('id', $sessionId)->delete();

        if ($deleted) {
            return redirect()->back()->with('success', 'Session terminated successfully.');
        } else {
            return redirect()->back()->with('error', 'Session not found or already terminated.');
        }
    }

    /**
     * Terminate all sessions except current.
     */
    public function destroyAll()
    {
        $currentSessionId = Session::getId();
        
        $deleted = DB::table('sessions')
            ->where('id', '!=', $currentSessionId)
            ->delete();

        return redirect()->back()->with('success', "Terminated {$deleted} sessions successfully.");
    }

    /**
     * Terminate all guest sessions.
     */
    public function destroyGuests()
    {
        $deleted = DB::table('sessions')
            ->whereNull('user_id')
            ->delete();

        return redirect()->back()->with('success', "Terminated {$deleted} guest sessions successfully.");
    }

    /**
     * Get session statistics.
     */
    private function getSessionStatistics()
    {
        $total = DB::table('sessions')->count();
        $authenticated = DB::table('sessions')->whereNotNull('user_id')->count();
        $guests = DB::table('sessions')->whereNull('user_id')->count();
        
        $now = Carbon::now();
        $activeLastHour = DB::table('sessions')
            ->where('last_activity', '>=', $now->subHour()->timestamp)
            ->count();

        $activeToday = DB::table('sessions')
            ->where('last_activity', '>=', $now->startOfDay()->timestamp)
            ->count();

        return [
            'total' => $total,
            'authenticated' => $authenticated,
            'guests' => $guests,
            'active_last_hour' => $activeLastHour,
            'active_today' => $activeToday
        ];
    }

    /**
     * Parse user agent string.
     */
    private function parseUserAgent($userAgent)
    {
        if (empty($userAgent)) {
            return [
                'browser' => 'Unknown',
                'platform' => 'Unknown',
                'device' => 'Unknown'
            ];
        }

        // Simple user agent parsing
        $browser = 'Unknown';
        $platform = 'Unknown';
        $device = 'Desktop';

        // Browser detection
        if (strpos($userAgent, 'Chrome') !== false) {
            $browser = 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            $browser = 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            $browser = 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            $browser = 'Edge';
        }

        // Platform detection
        if (strpos($userAgent, 'Windows') !== false) {
            $platform = 'Windows';
        } elseif (strpos($userAgent, 'Mac') !== false) {
            $platform = 'macOS';
        } elseif (strpos($userAgent, 'Linux') !== false) {
            $platform = 'Linux';
        } elseif (strpos($userAgent, 'Android') !== false) {
            $platform = 'Android';
            $device = 'Mobile';
        } elseif (strpos($userAgent, 'iOS') !== false) {
            $platform = 'iOS';
            $device = 'Mobile';
        }

        // Device detection
        if (strpos($userAgent, 'Mobile') !== false || strpos($userAgent, 'Android') !== false) {
            $device = 'Mobile';
        } elseif (strpos($userAgent, 'Tablet') !== false || strpos($userAgent, 'iPad') !== false) {
            $device = 'Tablet';
        }

        return [
            'browser' => $browser,
            'platform' => $platform,
            'device' => $device,
            'full' => $userAgent
        ];
    }

    /**
     * Decode session payload.
     */
    private function decodeSessionPayload($payload)
    {
        try {
            $data = base64_decode($payload);
            return unserialize($data);
        } catch (\Exception $e) {
            return ['error' => 'Unable to decode session data'];
        }
    }
}
