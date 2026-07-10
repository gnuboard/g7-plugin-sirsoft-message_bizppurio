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

    // 발송 엔진 오류 (API 클라이언트·토큰·발송 Job)
    'error' => [
        'credentials_missing' => 'Please set the Bizppurio ID and password first.',
        'token_issue_failed' => 'Failed to issue the Bizppurio authentication token.',
        'send_failed' => 'Failed to send the message.',
        'send_retryable' => 'Message delivery temporarily failed. (code: :code)',
        'invalid_response' => 'Unable to parse the Bizppurio response.',
        'kakao_credentials_missing' => 'Please set the ID and API key to use the Kakao management API.',
        'kakao_request_failed' => 'The Kakao management API request failed.',
    ],
];
