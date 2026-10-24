<?php

return [
    'stages' => [
        '0' => 'لم يبدأ',
        '1' => 'جاري التصميم',
        '2' => 'التصميم منتهي',
        '3' => 'الطباعة',
        '4' => 'جاهز للتسليم',
    ],
    'events' => [
        'design_started' => 'بدء التصميم',
        'design_completed' => 'إنهاء التصميم',
        'sent_to_printing' => 'إرسال للطباعة',
        'printing_location_changed' => 'تغيير المطبعة',
        'printing_completed' => 'إنهاء الطباعة',
        'undone' => 'التراجع',
    ],
    'permissions' => [
        'items' => [
            'list' => 'عرض منتجات الإنتاج',
            'undo' => 'التراجع في الإنتاج',
        ],
        'summary' => [
            'view' => 'عرض إحصائيات الإنتاج',
        ],
        'history' => [
            'view' => 'عرض تاريخ حركات الإنتاج',
        ],
        'design' => [
            'start' => 'بدء التصميم',
            'complete' => 'إنهاء التصميم',
        ],
        'printing' => [
            'send' => 'إرسال المنتجات للطباعة',
            'change_location' => 'تغيير مطبعة المنتج',
            'complete' => 'إنهاء الطباعة',
        ],
    ],
    'messages' => [
        'idempotency_required' => 'هيدر Idempotency-Key مطلوب وبحد أقصى 160 حرف.',
        'unexpected_fields' => 'الطلب يحتوي على حقول غير مدعومة.',
        'item_unavailable' => 'المنتج المحدد غير متاح للإنتاج.',
        'order_unavailable' => 'الأوردر غير متاح لتغيير الإنتاج.',
        'invalid_stage' => 'حالة المنتج الحالية لا تسمح بهذا الإجراء.',
        'invalid_location' => 'المطبعة المحددة غير نشطة أو غير موجودة.',
        'location_required' => 'يجب اختيار المطبعة.',
        'location_unchanged' => 'المنتج مُرسل بالفعل لنفس المطبعة.',
        'cannot_undo' => 'لا يمكن التراجع من المرحلة الحالية.',
        'invalid_action' => 'إجراء الإنتاج غير مدعوم.',
        'idempotency_reused' => 'تم استخدام مفتاح العملية من قبل مع بيانات مختلفة.',
        'start_design' => 'تم بدء التصميم.',
        'complete_design' => 'تم إنهاء التصميم.',
        'complete_and_send' => 'تم إنهاء التصميم وإرساله للطباعة.',
        'send_to_printing' => 'تم إرسال المنتجات للطباعة.',
        'change_location' => 'تم تغيير المطبعة.',
        'mark_ready' => 'المنتج جاهز للتسليم.',
        'undo' => 'تم التراجع للمرحلة السابقة.',
    ],
];
