<?php
namespace Modules\Customers\Services;

use App\Exceptions\Select\InvalidSelectRequestException;
use Illuminate\Database\Eloquent\Builder;
use Modules\Customers\Enums\CustomerStatus;
use Modules\Customers\Models\Customer;

class CustomerSelectService
{
    public function getAll(?string $filter = null): array
    {
        if ($filter !== null) {
            throw new InvalidSelectRequestException();
        }

        return $this->options(Customer::query());
    }

    public function getActive(?string $filter = null): array
    {
        if ($filter !== null && $filter !== 'active') {
            throw new InvalidSelectRequestException();
        }

        return $this->options(Customer::query()->where('status', CustomerStatus::ACTIVE->value));
    }

    private function options(Builder $query): array
    {
        return $query->orderBy('name')->orderBy('id')
            ->get(['id', 'name', 'phone'])
            ->map(static fn (Customer $customer): array => [
                'value' => $customer->id,
                'label' => $customer->name.' - '.$customer->phone,
            ])->all();
    }
}
