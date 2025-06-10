<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Show the admin dashboard.
     */
    public function index()
    {
        $stats = [
            'total_users' => User::count(),
            'total_accounts' => Account::count(),
            'total_transactions' => Transaction::count(),
            'recent_users' => User::latest()->take(5)->get(),
            'recent_transactions' => Transaction::with(['account', 'category'])
                ->latest()
                ->take(10)
                ->get(),
        ];

        return view('admin.dashboard', compact('stats'));
    }
}
