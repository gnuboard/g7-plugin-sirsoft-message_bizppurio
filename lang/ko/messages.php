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

    // 알림 채널 메타 (core.notification.filter_available_channels)
    'channels' => [
        'source_label' => '비즈뿌리오',
        'sms' => [
            'name' => 'SMS/LMS 문자',
            'description' => '비즈뿌리오를 통해 문자(SMS/LMS)로 알림을 발송합니다.',
        ],
        'alimtalk' => [
            'name' => '카카오 알림톡',
            'description' => '비즈뿌리오를 통해 카카오 알림톡으로 알림을 발송합니다.',
        ],
    ],

    // 채널 준비 상태 사유 (core.notification.channel_readiness)
    'readiness' => [
        'sms_credentials_missing' => '비즈뿌리오 아이디와 비밀번호를 설정하세요.',
        'sms_sender_number_missing' => '발신번호를 설정하세요.',
        'alimtalk_api_key_missing' => '카카오 관리 API 키를 설정하세요.',
        'alimtalk_sender_key_missing' => '알림톡 발신프로필 키를 설정하세요.',
    ],

    // 설정 검증 — 운영(live) 환경 필수 자격증명 항목 라벨 (validation.attributes 병합용)
    'settings' => [
        'bizppurio_id_attribute' => '비즈뿌리오 아이디',
        'password_attribute' => '비밀번호',
        'sender_number_attribute' => '발신번호',
    ],

    // webhook(URL PUSH) 리포트 수신
    'webhook' => [
        'received' => '리포트를 수신했습니다.',
    ],

    // 발송 엔진 오류 (API 클라이언트·토큰·발송 Job)
    'error' => [
        'credentials_missing' => '비즈뿌리오 아이디와 비밀번호를 먼저 설정하세요.',
        'token_issue_failed' => '비즈뿌리오 인증 토큰 발급에 실패했습니다.',
        'send_failed' => '메시지 발송 요청에 실패했습니다.',
        'send_retryable' => '메시지 발송이 일시적으로 실패했습니다. (코드: :code)',
        'invalid_response' => '비즈뿌리오 응답을 해석할 수 없습니다.',
        'kakao_credentials_missing' => '카카오 관리 API 사용을 위해 아이디와 API 키를 먼저 설정하세요.',
        'kakao_request_failed' => '카카오 관리 API 요청에 실패했습니다.',
        'sender_key_missing' => '알림톡 발신프로필 키를 먼저 설정하세요.',
    ],

    // 알림↔알림톡 템플릿 연동 (NotificationBindingController 응답)
    'binding' => [
        'saved' => '알림톡 연동을 저장했습니다.',
        'removed' => '알림톡 연동을 해제했습니다.',
    ],
];
