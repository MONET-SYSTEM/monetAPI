<?php

namespace App\Services;

use App\Models\Account;
use App\Models\AccountType;
use App\Models\Currency;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Http\Request;

class AccountService
{
    public function getAccountsByUser(User $user, Request $request): Collection
    {
        $query = Account::query();
        
        // Filter by user_id
        $query->where('user_id', $user->id);
        
        // Apply search filter if provided
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where('name', 'LIKE', "%{$search}%");
        }
        
        // Apply ordering and get results
        return $query->orderBy('name')->get();
    }

    public function getAccountByUserUuid(User $user, string $uuid): Account
    {
        $account = Account::where([
            'uuid' => $uuid,
            'user_id' => $user->id
        ])->first();

        if (!$account) {
            abort(404, __('app.data_not_found', ['data' => __('app.account')]));
        }

        return  $account;
    }

    public function create(User $user, object $request): Account
    {
        $accountType = AccountType::where([
            'uuid' => $request->account_type,
            'active' => 1
        ])->first();

        if (!$accountType) {
            abort(404, __('app.data_not_found', ['data' => __('app.account_type')]));
        }

        $currency = Currency::where([
            'uuid' => $request->currency,
            'active' => 1
        ])->first();

        if (!$currency) {
            abort(404, __('app.data_not_found', ['data' => __('app.currency')]));
        }

        return Account::create([
            'user_id' => $user->id,
            'account_type_id' => $accountType->id,
            'currency_id' => $currency->id,
            'name' => $request->name,
            'initial_balance' => $request->initial_balance,
            'colour_code' => $request->colour_code ?? null,
            'active' => 1,
        ]);
    }

    public function update(Account $account, object $request): Account
    {
        $accountType = AccountType::where([
            'uuid' => $request->account_type,
            'active' => 1
        ])->first();

        if (!$accountType) {
            abort(404, __('app.data_not_found', ['data' => __('app.account_type')]));
        }

        $currency = Currency::where([
            'uuid' => $request->currency,
            'active' => 1
        ])->first();

        if (!$currency) {
            abort(404, __('app.data_not_found', ['data' => __('app.currency')]));
        }

        $account->account_type_id = $accountType->id;
        $account->currency_id = $currency->id;
        $account->name = $request->name;
        $account->initial_balance = $request->initial_balance;
        $account->colour_code = $request->colour_code;
        $account->active = $request->active;
        $account->updated_at = Carbon::now();
        $account->update();

        return $account;
    }

    public function delete(Account $account, bool $forceDelete = false): bool
    {
        // Check if account has any transactions
        $transactionCount = $account->transactions()->count();
        
        if ($transactionCount > 0 && !$forceDelete) {
            throw new \Exception(
                "Cannot delete account '{$account->name}' because it has {$transactionCount} associated transaction(s). " .
                "Please delete all transactions first or use force delete if you want to remove all data."
            );
        }
        
        // If forceDelete is true or no transactions exist, proceed with deletion
        if ($transactionCount > 0 && $forceDelete) {
            // Soft delete all associated transactions first to maintain data integrity
            $account->transactions()->delete();
        }
        
        $account->delete();
        return true;
    }
}