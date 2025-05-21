<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AccountController extends Controller
{
    // Only allow logged-in users to access these actions.
    public function __construct()
    {
        $this->middleware('auth');
    }

    // List all accounts.
    public function index()
    {
        // Get every account from the database.
        $accounts = Account::all();

        // Show the accounts list in the AdminLTE-styled view.
        return view('admin.accounts.index', compact('accounts'));
    }

    // Show details for a single account.
    public function show(Account $account)
    {   
        // Get the previous account (by ID)
        $previous = Account::where('id', '<', $account->id)->orderBy('id', 'desc')->first();

        // Get the next account (by ID)
        $next = Account::where('id', '>', $account->id)->orderBy('id', 'asc')->first();

        // Display a view with this account's details.
        return view('admin.accounts.show', compact('account', 'previous', 'next'));
    }    // Display the form to create a new account.
    public function create()
    {
        // Fetch account types and currencies from the database.
        $accountTypes = AccountType::all();
        $currencies   = Currency::all();
        
        // Get list of users for admin to select from
        // You might want to add a role check here for security
        $users = \App\Models\User::all();

        // Pass the collections to the view.
        return view('admin.accounts.create', compact('accountTypes', 'currencies', 'users'));
    }    public function store(Request $request)
    {
        $request->validate([
            'account_type'    => 'required|exists:account_types,uuid',
            'currency'        => 'required|exists:currencies,uuid',
            'name'            => 'required|string|max:255',
            'initial_balance' => 'required|numeric',
            'colour_code'     => 'nullable|string|max:255',
            'user_id'         => 'nullable|exists:users,uuid', // Make user_id optional, will use Auth::id() if not provided
        ]);
    
        // Convert UUIDs to integer IDs
        $accountType = \App\Models\AccountType::where('uuid', $request->account_type)->first();
        $currency    = \App\Models\Currency::where('uuid', $request->currency)->first();
    
        if (!$accountType || !$currency) {
            return redirect()->back()->withErrors(__('app.invalid_account'));
        }
        
        // If user_id is provided and the current user is an admin (you may need to implement role checking)
        // then use the provided user_id, otherwise use the currently authenticated user's ID
        $userId = Auth::id(); // Default to current user
        
        if ($request->user_id) {
            $selectedUser = \App\Models\User::where('uuid', $request->user_id)->first();
            if ($selectedUser) {
                $userId = $selectedUser->id;
            }
        }
    
        Account::create([
            'user_id'         => $userId,
            'account_type_id' => $accountType->id, 
            'currency_id'     => $currency->id,    
            'name'            => $request->name,
            'initial_balance' => $request->initial_balance,
            'colour_code'     => $request->colour_code,
            'active'          => 1,
        ]);
    
        return redirect()->route('admin.accounts.index')->with('success', __('app.account_create'));
    }

    // Display the form to edit an existing account.
    public function edit(Account $account)
    {   
        
        // Fetch account types and currencies from the database.
        $accountTypes = AccountType::all();
        $currencies   = Currency::all();

        // Get the previous account (by ID)
        $previousAccount = Account::where('id', '<', $account->id)->orderBy('id', 'desc')->first();

        // Get the next account (by ID)
        $nextAccount = Account::where('id', '>', $account->id)->orderBy('id', 'asc')->first();

        // Show the form pre-filled with the account's current data and pass account types and currencies.
        return view('admin.accounts.edit', compact('account', 'accountTypes', 'currencies', 'previousAccount', 'nextAccount'));
    }

    // Update an existing account's data.
    public function update(Request $request, Account $account)
    {
        // Validate the updated data.
        $request->validate([
            'account_type'    => 'required|exists:account_types,uuid',
            'currency'        => 'required|exists:currencies,uuid',
            'name'            => 'required|string|max:255',
            'initial_balance' => 'required|numeric',
            'colour_code'     => 'nullable|string|max:255',
            'active'          => 'nullable|boolean',
        ]);

        // Update the account's data.
        $account->update([
            'account_type'    => $request->account_type,
            'currency'        => $request->currency,
            'name'            => $request->name,
            'initial_balance' => $request->initial_balance,
            'colour_code'     => $request->colour_code,
            'active'          => $request->has('active') ? $request->active : $account->active,
        ]);

        // Redirect to the accounts list with a success message.
        return redirect()->route('admin.accounts.index')->with('success', __('app.account_update'));
    }

    // Delete an account.
    public function destroy(Account $account)
    {
        // Remove the account from the database.
        $account->forceDelete(); // Permanently deletes the record
        return redirect()->route('admin.accounts.index')->with('success', __('app.account_delete'));
    }

    public function trends()
    {
        // Get counts grouped by account type
        $accountTypesStats = Account::selectRaw('account_type_id, COUNT(*) as count')
            ->groupBy('account_type_id')
            ->with('account_type')
            ->get()
            ->map(function($item) {
                return [
                    'name' => $item->account_type->name,
                    'count' => $item->count,
                ];
            })->toArray();

        // Get counts grouped by currency
        $currencyStats = Account::selectRaw('currency_id, COUNT(*) as count')->groupBy('currency_id')->with('currency')->get()->map(function($item) {
                return [
                    'name'  => $item->currency->name,
                    'code'  => $item->currency->code,
                    'count' => $item->count,
                ];
            })->toArray();

        return view('admin.accounts.trends', compact('accountTypesStats', 'currencyStats'));
    }
}
