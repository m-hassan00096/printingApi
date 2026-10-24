<?php
return [
    'status' => ['active' => 'Delivered', 'cancelled' => 'Cancelled'],
    'permissions' => ['ready.view' => 'View ready orders', 'list' => 'View deliveries',
        'view' => 'View delivery details', 'create' => 'Deliver orders', 'cancel' => 'Cancel deliveries'],
    'messages' => [
        'delivered' => 'Order delivered successfully.',
        'cancelled' => 'Delivery cancelled and its collection reversed if present.',
        'idempotency_required' => 'Idempotency-Key is required.',
        'idempotency_reused' => 'Idempotency-Key was already used for another request.',
        'unexpected_fields' => 'Unexpected request fields.',
        'invalid_date_range' => 'End date must not precede start date.',
        'order_unavailable' => 'Order is not eligible for delivery.',
        'order_not_delivered' => 'Order is not currently delivered.',
        'not_fully_ready' => 'All order items must be ready before delivery.',
        'already_delivered' => 'Order was already delivered.',
        'already_cancelled' => 'Delivery has already been cancelled.',
        'invalid_collection' => 'Delivery collection is invalid.',
        'credit_confirmation_required' => 'Confirm customer credit before posting.',
        'funds_confirmation_required' => 'Confirm external funds settlement before cancellation.',
    ],
];
