<?php
return [
    'balance_types' => ['zero' => 'Zero', 'debit' => 'Debit', 'credit' => 'Credit'],
    'payment_methods' => ['cash' => 'Cash', 'bank_transfer' => 'Bank transfer', 'e_wallet' => 'E-wallet'],
    'collection_sources' => ['general' => 'General collection', 'order_deposit' => 'Order deposit', 'delivery' => 'Delivery'],
    'collection_statuses' => ['posted' => 'Posted', 'reversed' => 'Reversed'],
    'ledger_types' => ['order_debt' => 'Order debt', 'collection' => 'Collection', 'order_cancellation' => 'Order cancellation', 'collection_reversal' => 'Collection reversal', 'refund' => 'Refund', 'opening_balance' => 'Opening balance'],
    'messages' => [
        'customer_not_found' => 'Customer not found', 'amount_out_of_range' => 'Amount out of range',
        'duplicate_operation' => 'Operation was already posted', 'idempotency_required' => 'Idempotency key is required',
        'idempotency_reused' => 'Idempotency key was reused with a different request',
        'missing_order_liability' => 'Original order liability was not found',
        'opening_exists' => 'Opening balance already recorded', 'opening_created' => 'Opening balance recorded',
        'collection_created' => 'Collection recorded', 'collection_reversed' => 'Collection reversed',
        'collection_already_reversed' => 'Collection already reversed', 'refund_created' => 'Refund recorded',
        'insufficient_credit' => 'Insufficient customer credit', 'invalid_destination' => 'Payment destination is unavailable',
        'invalid_order' => 'Order does not belong to this customer', 'positive_amount' => 'Amount must be greater than zero',
    ],
    'permissions' => [
        'balances' => ['view' => 'View customer balances'],
        'ledger' => ['view' => 'View customer ledger'],
        'opening_balances' => ['create' => 'Create opening balance'],
        'collections' => [
            'view' => 'View collections',
            'create' => 'Create collection',
            'reverse' => 'Reverse collection',
        ],
        'refunds' => [
            'view' => 'View refunds',
            'create' => 'Create refund',
        ],
    ],
];
