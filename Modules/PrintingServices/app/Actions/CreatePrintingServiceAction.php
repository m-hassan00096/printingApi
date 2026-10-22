<?php

namespace Modules\PrintingServices\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\PrintingServices\Models\PrintingService;
use Modules\PrintingServices\Services\PriceTierRuleService;
use Modules\PrintingServices\Support\ServiceName;

class CreatePrintingServiceAction
{
    public function __construct(private readonly PriceTierRuleService $tierRules) {}

    public function execute(array $data): PrintingService
    {
        try {
            return DB::transaction(function () use ($data): PrintingService {
            $name = ServiceName::normalize($data['name']);
            $service = new PrintingService();
            $service->fill([
                'name' => $name,
                'base_price' => $data['basePrice'] ?? null,
                'status' => $data['status'],
            ]);
            $service->live_name_hash = ServiceName::hash($name);

            $tiers = $data['priceTiers'] ?? [];
            $final = [];
            foreach ($tiers as $tier) {
                $typeId = isset($tier['customerTypeId']) ? (int) $tier['customerTypeId'] : null;
                $this->tierRules->assertCustomerType($typeId);
                $final[] = [
                    'customerTypeId' => $typeId,
                    'minQuantity' => isset($tier['minQuantity']) ? (int) $tier['minQuantity'] : null,
                    'maxQuantity' => isset($tier['maxQuantity']) ? (int) $tier['maxQuantity'] : null,
                ];
            }
            $this->tierRules->assertValidFinalRanges($final);
            $service->save();

            foreach ($tiers as $tier) {
                $service->priceTiers()->create([
                    'customer_type_id' => $tier['customerTypeId'] ?? null,
                    'min_quantity' => $tier['minQuantity'] ?? null,
                    'max_quantity' => $tier['maxQuantity'] ?? null,
                    'price' => $tier['price'],
                ]);
            }

            return $service->load('priceTiers');
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => 'The service name is already in use.',
            ]);
        }
    }
}

