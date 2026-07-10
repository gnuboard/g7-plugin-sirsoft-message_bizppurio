<?php

return [
    // 발송 채널 (DispatchChannel enum)
    'channel' => [
        'sms' => 'SMS',
        'lms' => 'LMS',
        'alimtalk' => '알림톡',
    ],

    // 발송 상태 (DispatchStatus enum)
    'status' => [
        'pending' => '대기',
        'sent' => '발송중',
        'success' => '성공',
        'failed' => '실패',
    ],

    // 발송 출처 (DispatchSource enum)
    'source' => [
        'auto' => '자동',
        'manual' => '수동',
        'bulk' => '대량',
    ],

    // 결과코드 분류 (ResultCategory enum)
    'result_category' => [
        'success' => '성공',
        'retry' => '재시도',
        'permanent_failure' => '영구 실패',
        'balance_low' => '잔액 부족',
    ],
];
