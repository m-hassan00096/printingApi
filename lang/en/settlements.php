<?php
return [
    'saved' => 'The financial settlement result has been recorded.',
    'empty_scope' => 'There is no delivered work or opening balance to settle in this scope.',
    'excess_payment' => 'The payment exceeds this settlement balance. Record additional advances through Collections.',
    'payment_required' => 'A settlement can be recorded without cash, leaving the debt outstanding.',
    'invalid_discount' => 'Discount exceeds delivered-work debt or real account debt, or would create customer credit.',
    'excess_after_discount' => 'The collection exceeds the remaining balance after discount.',
    'discount_note' => 'Fixed discount in settlement :number — :reason',
    'stale_preview' => 'The customer account changed after this preview. Refresh before confirming; no new collection was posted.',
    'collection_note' => 'Collection for settlement :number',
    'status' => [
        'closed' => 'Fully settled within scope', 'partial' => 'Partially settled - balance remains',
        'credit' => 'Nothing due - customer credit retained', 'needs_review' => 'Review required after a subsequent transaction',
    ],
    'report' => [
        'cm' => 'cm', 'delivery_cancellation' => 'Order delivery canceled',
        'collection' => 'Customer payment received', 'collection_reversal' => 'Previous collection canceled',
        'refund' => 'Amount refunded to customer', 'opening_balance' => 'Opening balance',
        'settlement_discount' => 'Settlement discount',
    ],
];
