<?php

namespace Modules\Settings\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Modules\Settings\Enums\PaymentDestinationStatus;

class PaymentDestinationDefaultService
{
    /**
     * The dedicated lock row exists before the first payment destination.
     * Every Settings mutation for a type must acquire it within a transaction,
     * preventing two concurrent requests from independently choosing defaults.
     */
    private function synchronized(string $table, callable $callback): mixed
    {
        return DB::transaction(function () use ($table, $callback): mixed {
            DB::table('settings_payment_default_locks')
                ->where('name', $table)
                ->lockForUpdate()
                ->firstOrFail();

            return $callback();
        }, 3);
    }

    /**
     * @param class-string<Model> $modelClass
     * @param array<string, mixed> $attributes
     */
    public function create(string $modelClass, string $table, array $attributes, bool $wantsDefault): Model
    {
        return $this->synchronized($table, function () use ($modelClass, $attributes, $wantsDefault): Model {
            $attributes['is_default'] = false;
            $record = $modelClass::query()->create($attributes);

            if ($this->isActive($record) && ($wantsDefault || ! $this->hasEligibleDefault($modelClass))) {
                $this->promote($modelClass, $record);
            }

            return $record->refresh();
        });
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function update(Model $model, string $table, array $attributes, ?bool $wantsDefault): Model
    {
        $modelClass = $model::class;

        return $this->synchronized($table, function () use ($modelClass, $model, $attributes, $wantsDefault): Model {
            $record = $modelClass::query()
                ->whereKey($model->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $wasDefault = (bool) $record->is_default;
            $record->fill($attributes);
            $record->is_default = $wasDefault && $this->isActive($record);

            if ($wantsDefault === false && $wasDefault) {
                $record->is_default = false;
            }

            $record->save();

            if ($this->isActive($record) && $wantsDefault === true) {
                $this->promote($modelClass, $record);
            } elseif (! $this->hasEligibleDefault($modelClass)) {
                $this->promoteOldestActive($modelClass);
            }

            return $record->refresh();
        });
    }

    public function delete(Model $model, string $table): void
    {
        $modelClass = $model::class;

        $this->synchronized($table, function () use ($modelClass, $model): void {
            $record = $modelClass::query()
                ->whereKey($model->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            // The owning Finance module must add historic-reference restrictions
            // before accounts used by receipts or advances can be deleted.
            if ($record->is_default) {
                $record->is_default = false;
                $record->save();
            }

            $record->delete();

            if (! $this->hasEligibleDefault($modelClass)) {
                $this->promoteOldestActive($modelClass);
            }
        });
    }

    /** @param class-string<Model> $modelClass */
    private function hasEligibleDefault(string $modelClass): bool
    {
        return $modelClass::query()
            ->where('status', PaymentDestinationStatus::ACTIVE->value)
            ->where('is_default', true)
            ->exists();
    }

    /** @param class-string<Model> $modelClass */
    private function promoteOldestActive(string $modelClass): void
    {
        // Interim deterministic choice; exact replacement priority awaits approval.
        $candidate = $modelClass::query()
            ->where('status', PaymentDestinationStatus::ACTIVE->value)
            ->orderBy('id')
            ->first();

        if ($candidate !== null) {
            $this->promote($modelClass, $candidate);
        }
    }

    /** @param class-string<Model> $modelClass */
    private function promote(string $modelClass, Model $selected): void
    {
        $existing = $modelClass::query()
            ->where('is_default', true)
            ->whereKeyNot($selected->getKey())
            ->get();

        foreach ($existing as $record) {
            $record->is_default = false;
            $record->save();
        }

        if (! $selected->is_default) {
            $selected->is_default = true;
            $selected->save();
        }
    }

    private function isActive(Model $record): bool
    {
        return $record->status === PaymentDestinationStatus::ACTIVE;
    }
}
