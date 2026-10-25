<?php
return [
    'status' => ['active' => 'تم التسليم', 'cancelled' => 'ملغي'],
    'permissions' => [
        'ready' => ['view' => 'عرض الجاهز للتسليم'],
        'list' => 'عرض سجل التسليمات',
        'view' => 'عرض تفاصيل التسليم',
        'create' => 'تسليم الأوردر',
        'cancel' => 'إلغاء التسليم',
    ],
    'messages' => [
        'delivered' => 'تم تسليم الأوردر بنجاح.',
        'cancelled' => 'تم إلغاء التسليم وعكس التحصيل المرتبط به إن وجد.',
        'idempotency_required' => 'مفتاح العملية مطلوب.',
        'idempotency_reused' => 'تم استخدام نفس مفتاح العملية ببيانات مختلفة.',
        'unexpected_fields' => 'يوجد حقول غير متوقعة.',
        'invalid_date_range' => 'نهاية المدة يجب ألا تسبق بدايتها.',
        'order_unavailable' => 'الأوردر غير متاح للتسليم.',
        'order_not_delivered' => 'الأوردر غير مسلم حاليًا.',
        'not_fully_ready' => 'كل منتجات الأوردر يجب أن تكون جاهزة قبل التسليم.',
        'already_delivered' => 'تم تسليم الأوردر بالفعل.',
        'already_cancelled' => 'عملية التسليم ملغاة بالفعل.',
        'invalid_collection' => 'إيصال التحصيل المرتبط بالتسليم غير صحيح.',
        'credit_confirmation_required' => 'يلزم تأكيد تحويل الرصيد إلى دائن.',
        'funds_confirmation_required' => 'يجب تأكيد تسوية المبلغ المستلم قبل إلغاء التسليم.',
    ],
];
