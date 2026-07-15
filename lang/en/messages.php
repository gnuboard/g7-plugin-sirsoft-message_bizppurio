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

    // 알림 채널 메타 (core.notification.filter_available_channels)
    'channels' => [
        'source_label' => 'Bizppurio',
        'sms' => [
            'name' => 'SMS/LMS Text',
            'description' => 'Send notifications as SMS/LMS text messages via Bizppurio.',
        ],
        'alimtalk' => [
            'name' => 'Kakao Alimtalk',
            'description' => 'Send notifications as Kakao Alimtalk messages via Bizppurio.',
        ],
    ],

    // 채널 준비 상태 사유 (core.notification.channel_readiness)
    'readiness' => [
        'sms_credentials_missing' => 'Please set the Bizppurio ID and password.',
        'sms_sender_number_missing' => 'Please set the sender number.',
        'alimtalk_api_key_missing' => 'Please set the Kakao management API key.',
        'alimtalk_sender_key_missing' => 'Please set the Alimtalk sender profile key.',
    ],

    // 설정 검증 — 운영(live) 환경 필수 자격증명 항목 라벨 (validation.attributes 병합용)
    'settings' => [
        'bizppurio_id_attribute' => 'Bizppurio ID',
        'password_attribute' => 'Password',
        'sender_number_attribute' => 'Sender Number',
    ],

    // webhook(URL PUSH) 리포트 수신
    'webhook' => [
        'received' => 'Report received.',
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
        'sender_key_missing' => 'Please set the alimtalk sender profile key first.',
    ],

    // Alimtalk template management (Phase 5)
    'template' => [
        // Action result messages
        'created' => 'The alimtalk template has been registered.',
        'created_requested' => 'The alimtalk template has been registered and inspection requested.',
        'updated' => 'The alimtalk template has been updated.',
        'deleted' => 'The alimtalk template has been deleted.',
        'requested' => 'Inspection has been requested.',
        'request_canceled' => 'The inspection request has been canceled.',
        'stopped' => 'The template has been stopped.',
        'resumed' => 'The template has been resumed.',
        'approval_canceled' => 'The approval has been canceled.',
        'released' => 'The template has been released from dormancy.',

        // Status badges (serviceStatus)
        'status' => [
            'sendable' => 'Sendable',
            'inspecting' => 'Inspecting',
            'rejected' => 'Rejected',
            'uninspected' => 'Not inspected',
            'stopped' => 'Stopped',
            'blocked' => 'Blocked',
            'dormant' => 'Dormant',
            'unknown' => 'Unknown',
        ],

        // Template types (templateEmphasizeType)
        'emphasize_type' => [
            'none' => 'Basic',
            'text' => 'Highlighted',
            'image' => 'Image',
            'item_list' => 'Item list',
        ],

        // Validation attribute labels (FormRequest attributes)
        'fields' => [
            'name' => 'Template name',
            'content' => 'Template content',
            'category' => 'Category',
            'emphasize_type' => 'Template type',
            'code' => 'Template code',
            'title' => 'Highlight title',
            'subtitle' => 'Highlight subtitle',
            'image' => 'Image',
            'image_name' => 'Image file name',
            'image_url' => 'Image URL',
        ],
    ],

    // Notification-to-alimtalk template binding (NotificationBindingController responses)
    'binding' => [
        'saved' => 'Alimtalk binding saved.',
        'removed' => 'Alimtalk binding removed.',
    ],
];
