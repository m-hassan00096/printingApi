<?php

namespace Modules\PrintingServices\Actions;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\PrintingServices\Enums\PriceTierActionStatus;
use Modules\PrintingServices\Models\PrintingService;
use Modules\PrintingServices\Services\PriceTierRuleService;
use Modules\PrintingServices\Support\ServiceName;

class UpdatePrintingServiceAction
{
    public function __construct(private readonly PriceTierRuleService $tierRules) {}

    public function execute(PrintingService $service, array $data): PrintingService
    {
        try {
            return DB::transaction(function () use ($service, $data): PrintingService {
            $locked = PrintingService::query()->whereKey($service->id)->lockForUpdate()->firstOrFail();
            $stored = $locked->priceTiers()->get()->keyBy('id');
            $final = [];
            foreach ($stored as $id => $tier) {
                $final[$id] = [
                    'customerTypeId' => $tier->customer_type_id === null ? null : (int) $tier->customer_type_id,
                    'minQuantity' => $tier->min_quantity,
                    'maxQuantity' => $tier->max_quantity,
                ];
            }

            $commands = $data['priceTiers'] ?? [];
            $seen = [];
            $new = 0;
            foreach ($commands as $index => $command) {
                $action = PriceTierActionStatus::from((int) $command['actionStatus']);
                if ($action === PriceTierActionStatus::UNCHANGED && ! isset($command['priceTierId'])) {
                    continue;
                }
                if ($action !== PriceTierActionStatus::CREATE) {
                    $id = (int) $command['priceTierId'];
                    if (! $stored->has($id)) {
                        throw ValidationException::withMessages([
                            'priceTiers' => __('printing_services.messages.tier_not_found'),
                        ]);
                    }
                    if (isset($seen[$id])) {
                        throw ValidationException::withMessages([
                            'priceTiers' => __('printing_services.messages.duplicate_tier_command'),
                        ]);
                    }
                    $seen[$id] = true;
                }
                if ($action === PriceTierActionStatus::UNCHANGED) {
                    continue;
                }
                if ($action === PriceTierActionStatus::DELETE) {
                    unset($final[$id]);
                    continue;
                }

                $this->tierRules->assertPrice($command['price'], "priceTiers.{$index}.price");
                $typeId = isset($command['customerTypeId']) ? (int) $command['customerTypeId'] : null;
                $this->tierRules->assertCustomerType($typeId, "priceTiers.{$index}.customerTypeId");
                $entry = [
                    'customerTypeId' => $typeId,
                    'minQuantity' => isset($command['minQuantity']) ? (int) $command['minQuantity'] : null,
                    'maxQuantity' => isset($command['maxQuantity']) ? (int) $command['maxQuantity'] : null,
                ];
                if ($action === PriceTierActionStatus::CREATE) {
                    $final['new:'.++$new] = $entry;
                } else {
                    $final[$id] = $entry;
                }
            }

            $this->tierRules->assertValidFinalRanges(array_values($final));

            if (array_key_exists('name', $data)) {
                $name = ServiceName::normalize($data['name']);
                $locked->name = $name;
                $locked->live_name_hash = ServiceName::hash($name);
            }
            if (array_key_exists('basePrice', $data)) {
                $locked->base_price = $data['basePrice'];
            }
            if (array_key_exists('status', $data)) {
                $locked->status = $data['status'];
            }
            if ($locked->isDirty()) {
                $locked->save();
            }

            foreach ($commands as $command) {
                $action = PriceTierActionStatus::from((int) $command['actionStatus']);
                if ($action === PriceTierActionStatus::UNCHANGED) {
                    continue;
                }
                if ($action === PriceTierActionStatus::CREATE) {
                    $locked->priceTiers()->create([
                        'customer_type_id' => $command['customerTypeId'] ?? null,
                        'min_quantity' => $command['minQuantity'] ?? null,
                        'max_quantity' => $command['maxQuantity'] ?? null,
                        'price' => $command['price'],
                    ]);
                    continue;
                }

                $tier = $stored->get((int) $command['priceTierId']);
                if ($action === PriceTierActionStatus::DELETE) {
                    $tier->delete();
                } else {
                    $tier->fill([
                        'customer_type_id' => $command['customerTypeId'],
                        'min_quantity' => $command['minQuantity'],
                        'max_quantity' => $command['maxQuantity'],
                        'price' => $command['price'],
                    ]);
                    if ($tier->isDirty()) {
                        $tier->save();
                    }
                }
            }

            return $locked->refresh()->load('priceTiers');
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => __('printing_services.messages.duplicate_name'),
            ]);
        }
    }
}

