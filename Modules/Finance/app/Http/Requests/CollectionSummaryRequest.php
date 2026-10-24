<?php
namespace Modules\Finance\Http\Requests;

class CollectionSummaryRequest extends CollectionIndexRequest
{
    protected function filterRules(): array
    {
        $rules = parent::filterRules();
        // The summary always represents posted collections only.
        unset($rules['status']);

        return $rules;
    }

    protected function sortableFields(): array
    {
        return [];
    }
}
