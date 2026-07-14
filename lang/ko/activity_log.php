<?php

return [
    // 액션 라벨 — 고유 action 은 전체 dotted 키로 정의(코어 범용 세그먼트 충돌 회피, 계획서 §3).
    // ActivityLog::getActionLabelAttribute 1단계(플러그인 lang 전체 액션 키)가 이 배열을 먼저 해석.
    'action' => [
        'sirsoft-message_bizppurio' => [
            'alimtalk_template' => [
                'create' => '알림톡 템플릿 등록',
                'update' => '알림톡 템플릿 수정',
                'delete' => '알림톡 템플릿 삭제',
                'request' => '알림톡 템플릿 검수 요청',
                'cancel_request' => '알림톡 템플릿 검수 요청 취소',
                'stop' => '알림톡 템플릿 중지',
                'reuse' => '알림톡 템플릿 중지 해제',
                'cancel_approval' => '알림톡 템플릿 승인 취소',
                'release' => '알림톡 템플릿 휴면 해제',
            ],
        ],
    ],

    // description 본문 — 표준 패턴 activity_log.description.X (DescriptionResolver 해석 대상).
    'description' => [
        'alimtalk_template_create' => '알림톡 템플릿 등록 (:template_name)',
        'alimtalk_template_update' => '알림톡 템플릿 수정 (:template_code)',
        'alimtalk_template_delete' => '알림톡 템플릿 삭제 (:template_code)',
        'alimtalk_template_request' => '알림톡 템플릿 검수 요청 (:template_code)',
        'alimtalk_template_cancel_request' => '알림톡 템플릿 검수 요청 취소 (:template_code)',
        'alimtalk_template_stop' => '알림톡 템플릿 중지 (:template_code)',
        'alimtalk_template_reuse' => '알림톡 템플릿 중지 해제 (:template_code)',
        'alimtalk_template_cancel_approval' => '알림톡 템플릿 승인 취소 (:template_code)',
        'alimtalk_template_release' => '알림톡 템플릿 휴면 해제 (:template_code)',
    ],
];
