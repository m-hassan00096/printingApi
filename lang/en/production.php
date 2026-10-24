<?php

return [
    'stages' => [
        '0' => 'Not started',
        '1' => 'Design in progress',
        '2' => 'Design completed',
        '3' => 'Printing',
        '4' => 'Ready for delivery',
    ],
    'events' => [
        'design_started' => 'Design started',
        'design_completed' => 'Design completed',
        'sent_to_printing' => 'Sent to printing',
        'printing_location_changed' => 'Printing location changed',
        'printing_completed' => 'Printing completed',
        'undone' => 'Moved back',
    ],
    'permissions' => [
        'items' => [
            'list' => 'View production items',
            'undo' => 'Undo production step',
        ],
        'summary' => [
            'view' => 'View production summary',
        ],
        'history' => [
            'view' => 'View production history',
        ],
        'design' => [
            'start' => 'Start item design',
            'complete' => 'Complete item design',
        ],
        'printing' => [
            'send' => 'Send to printing',
            'change_location' => 'Change printing location',
            'complete' => 'Complete printing',
        ],
    ],
    'messages' => [
        'idempotency_required' => 'Idempotency-Key header is required (max 160 characters).',
        'unexpected_fields' => 'Unexpected request fields.',
        'item_unavailable' => 'The selected product is unavailable for production.',
        'order_unavailable' => 'This order is not eligible for production.',
        'invalid_stage' => 'The product\'s current stage does not allow this action.',
        'invalid_location' => 'This printing location is not active.',
        'location_required' => 'Choose a printing location.',
        'location_unchanged' => 'The selected printing location is already assigned.',
        'cannot_undo' => 'This production stage cannot be undone.',
        'invalid_action' => 'This production action is not supported.',
        'idempotency_reused' => 'This idempotency key has already been used for a different request.',
        'start_design' => 'Design started.',
        'complete_design' => 'Design completed.',
        'complete_and_send' => 'Design completed and sent to printing.',
        'send_to_printing' => 'Products sent to printing.',
        'change_location' => 'Printing location updated.',
        'mark_ready' => 'Product is ready for delivery.',
        'undo' => 'Product returned to the previous production stage.',
    ],
];
