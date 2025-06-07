<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CurrencyService
{
    public function getAll(object $request, ?int $pagination = null): Collection|LengthAwarePaginator
    {
        $currencies = Currency::orderBy('name')->where('active', 1);

        if ($request->search) {
            $search = $request->search;
            $currencies->where(function ($query) use ($search) {
                $query->where('code', 'LIKE', "%{$search}%")->orWhere('name', 'LIKE', "%{$search}%");
            });
        }

        return $pagination ? $currencies->paginate($pagination) : $currencies->get();
    }


    public function getByUuid(string $uuid): Currency
    {
        $currency = Currency::where([
            'active' => 1,
            'uuid' => $uuid
        ])->first();

        if (!$currency) {
            abort(404, __('app.data_not_found', [
                'data' => __('app.currency')
            ]));
        }

        return $currency;
    }

    public function create(array $data): Currency
    {
        $data['uuid'] = Str::uuid();
        return Currency::create($data);
    }

    public function update(Currency $currency, array $data): Currency
    {
        $currency->update($data);
        return $currency->fresh();
    }

    public function delete(Currency $currency): bool
    {
        // Soft delete - mark as inactive instead of hard delete
        // to maintain referential integrity with accounts
        return $currency->update(['active' => 0]);
    }

    public function getById(int $id): Currency
    {
        $currency = Currency::where([
            'active' => 1,
            'id' => $id
        ])->first();

        if (!$currency) {
            abort(404, __('app.data_not_found', [
                'data' => __('app.currency')
            ]));
        }

        return $currency;
    }
}