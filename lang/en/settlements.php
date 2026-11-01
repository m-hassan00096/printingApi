<?php
return [
    'saved' => 'The financial settlement result has been recorded.',
    'empty_scope' => 'There is no delivered work or opening balance to settle in this scope.',
    'excess_payment' => 'The payment exceeds this settlement balance. Record additional advances through Collections.',
    'payment_required' => 'Enter the amount actually received. Outstanding debt cannot be closed without payment.',
    'stale_preview' => 'The customer account changed after this preview. Refresh before confirming; no new collection was posted.',
    'collection_note' => 'Collection for settlement :number',
    'status' => [
        'closed' => 'Fully settled within scope', 'partial' => 'Partial payment - balance remains',
        'credit' => 'Nothing due - customer credit retained', 'needs_review' => 'Review required after a subsequent transaction',
    ],
    'report' => [
        'cm' => 'cm', 'delivery_cancellation' => 'Order delivery canceled',
        'collection' => 'Customer payment received', 'collection_reversal' => 'Previous collection canceled',
        'refund' => 'Amount refunded to customer', 'opening_balance' => 'Opening balance',
    ],
];
