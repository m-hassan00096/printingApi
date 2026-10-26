<?php

namespace Modules\Orders\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

trait ValidatesDraftPaymentPlan
{
    /**
     * Planned payment is metadata, not a Finance movement. Only complete
     * snapshots are accepted when saving a draft.
     */
    protected function draftPaymentRules(bool $confirmation): array
    {
        $amount = $this->input('plannedDepositAmount');
        $planning = $this->exists('plannedDepositAmount')
            || $this->exists('plannedPaymentMethod')
            || $this->exists('plannedPaymentDestinationId');

        $positive = is_string($amount)
            && preg_match('/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D', $amount) === 1
            && preg_match('/[1-9]/', $amount) === 1;
        $method = (string) $this->input('plannedPaymentMethod', '');

        return [
            'plannedDepositAmount' => [
                Rule::requiredIf($planning && ! $confirmation), 'nullable', 'string',
                'regex:/\A(?:0|[1-9][0-9]{0,9})(?:\.[0-9]{1,2})?\z/D',
                Rule::prohibitedIf($confirmation),
            ],
            'plannedPaymentMethod' => [
                Rule::requiredIf($planning && $positive && ! $confirmation),
                'nullable', 'integer', 'in:0,1,2',
                Rule::prohibitedIf($confirmation || ! $positive),
            ],
            'plannedPaymentDestinationId' => [
                Rule::requiredIf($planning && $positive && in_array($method, ['1', '2'], true) && ! $confirmation),
                'nullable', 'integer', 'min:1',
                Rule::prohibitedIf($confirmation || ! $positive || $method === '0'),
            ],
        ];
    }
}
