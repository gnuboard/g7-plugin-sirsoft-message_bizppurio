<?php

return [
    // 발송 채널 (DispatchChannel enum)
    'channel' => [
        'sms' => 'SMS',
        'lms' => 'LMS',
        'alimtalk' => 'Alimtalk',
    ],

    // 발송 상태 (DispatchStatus enum)
    'status' => [
        'pending' => 'Pending',
        'sent' => 'Sending',
        'success' => 'Success',
        'failed' => 'Failed',
    ],

    // 발송 출처 (DispatchSource enum)
    'source' => [
        'auto' => 'Automatic',
        'manual' => 'Manual',
        'bulk' => 'Bulk',
    ],

    // 결과코드 분류 (ResultCategory enum)
    'result_category' => [
        'success' => 'Success',
        'retry' => 'Retry',
        'permanent_failure' => 'Permanent Failure',
        'balance_low' => 'Insufficient Balance',
    ],
];
