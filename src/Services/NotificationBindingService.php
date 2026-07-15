<?php

declare(strict_types=1);

namespace Plugins\Sirsoft\MessageBizppurio\Services;

use Plugins\Sirsoft\MessageBizppurio\Exceptions\BizppurioApiException;
use Plugins\Sirsoft\MessageBizppurio\Models\BizppurioNotificationBinding;
use Plugins\Sirsoft\MessageBizppurio\Repositories\Contracts\BizppurioNotificationBindingRepositoryInterface;

/**
 * 이벤트↔알림톡 템플릿 연동(binding) 서비스 (계획서 §6-2, Phase 6 재설계 A).
 *
 * 알림 설정 알림톡 탭은 코어 기본 목록·편집 모달을 그대로 사용한다(⚑⚑ 결정 A). 연결 템플릿·
 * SMS 대체는 코어 편집 모달에 얹은 전용 칸에서 입력하고, 코어 [저장] 훅
 * (core.notification_template.filter_update_data/after_update)에 편승해 이 서비스의
 * bind/unbind 로 저장된다(별도 저장 버튼·우리 목록 API 없음).
 *
 * 이 서비스는 (1) 코어 편집 모달 전용 칸이 소비하는 조회 — 승인 템플릿 드롭다운(approvedTemplates)
 * 과 현재 연동 맵(all) — 및 (2) 저장 훅 리스너가 호출하는 bind/unbind 를 제공한다.
 *
 * 연결 대상 템플릿 드롭다운은 "발송 가능(승인) 상태" 템플릿만 노출한다 — 미승인 템플릿에
 * 연동해도 발송이 거부되기 때문. 승인 판정은 AlimtalkTemplateService 의 배지 매핑(RDY/ACT
 * = 발송가능)을 재사용한다.
 */
class NotificationBindingService
{
    /** 연동 대상 채널 (1차 알림톡 고정) */
    private const CHANNEL = 'alimtalk';

    /** 발송 가능(연결 허용) 카카오 템플릿 상태 — RDY(발송전)·ACT(정상) */
    private const SENDABLE_STATUSES = ['RDY', 'ACT'];

    /**
     * @param  BizppurioNotificationBindingRepositoryInterface  $bindings  연동 조회/저장
     * @param  AlimtalkTemplateService  $templates  카카오 승인 템플릿 조회
     */
    public function __construct(
        private readonly BizppurioNotificationBindingRepositoryInterface $bindings,
        private readonly AlimtalkTemplateService $templates,
    ) {}

    /**
     * 알림톡 채널의 모든 연동을 notification_type → 연동 정보 맵으로 반환합니다.
     *
     * 코어 편집 모달 전용 칸이 편집 중인 알림의 기존 연동(연결 템플릿·SMS 대체)을 프리필하는
     * 데 쓴다. 코어 목록은 코어가 렌더하므로(⚑⚑ 결정 A) 알림 정의와 조인하지 않고 binding
     * 만 내려준다. 편집 모달은 def.type 으로 이 맵을 조회한다.
     *
     * @return array<string, array<string, mixed>> notification_type 키의 연동 맵
     */
    public function all(): array
    {
        return $this->bindings->allByChannel(self::CHANNEL)
            ->keyBy('notification_type')
            ->map(fn (BizppurioNotificationBinding $binding) => [
                'notification_type' => $binding->notification_type,
                'template_code' => $binding->template_code,
                'template_name' => $binding->template_name,
                'fallback_sms_enabled' => (bool) $binding->fallback_sms_enabled,
            ])
            ->all();
    }

    /**
     * 연결 가능한(발송 가능/승인) 알림톡 템플릿 목록을 반환합니다 (연동 모달 드롭다운).
     *
     * 카카오 템플릿 목록 중 serviceStatus 가 RDY/ACT 인 항목만 노출한다. 미승인 템플릿에
     * 연동해도 발송이 거부되므로 애초에 선택지에서 제외한다.
     *
     * @return array<int, array{template_code: string, template_name: string}>
     *
     * @throws BizppurioApiException 자격증명 미설정·조회 실패 시
     */
    public function approvedTemplates(): array
    {
        // 승인 상태 필터는 kapi 에 status 파라미터로 위임하지 않고(상태 2종 조회 불가), 전체를
        // 받아 serviceStatus 로 거른다. 페이지네이션 대신 넉넉한 count 로 1회 조회한다.
        $result = $this->templates->list(['count' => 100]);

        return collect($result['templates'] ?? [])
            ->filter(fn (array $row) => in_array((string) ($row['serviceStatus'] ?? $row['status'] ?? ''), self::SENDABLE_STATUSES, true))
            ->map(fn (array $row) => [
                'template_code' => (string) ($row['templateCode'] ?? $row['code'] ?? ''),
                'template_name' => (string) ($row['templateName'] ?? $row['name'] ?? ''),
            ])
            ->filter(fn (array $row) => $row['template_code'] !== '')
            ->values()
            ->all();
    }

    /**
     * 알림에 알림톡 템플릿을 연결(생성/갱신)합니다 (연동 모달 저장).
     *
     * @param  string  $notificationType  코어 notification_definitions.type
     * @param  array<string, mixed>  $data  template_code / template_name / fallback_sms_enabled
     * @return BizppurioNotificationBinding 저장된 연동
     */
    public function bind(string $notificationType, array $data): BizppurioNotificationBinding
    {
        return $this->bindings->upsert($notificationType, self::CHANNEL, [
            'template_code' => (string) $data['template_code'],
            'template_name' => (string) $data['template_name'],
            'fallback_sms_enabled' => (bool) ($data['fallback_sms_enabled'] ?? false),
            'is_active' => true,
        ]);
    }

    /**
     * 알림의 알림톡 연동을 해제(삭제)합니다.
     *
     * @param  string  $notificationType  코어 notification_definitions.type
     */
    public function unbind(string $notificationType): void
    {
        $this->bindings->delete($notificationType, self::CHANNEL);
    }

    /**
     * 코어 편집 모달 전용 칸이 넘긴 값으로 연동을 반영합니다 (저장 훅 편승).
     *
     * 코어 알림 템플릿 [저장] 훅(after_update)에서 호출된다. 연결 템플릿 코드가 비어 있으면
     * 연동 해제, 있으면 생성/갱신한다. 이 "빈 값=해제" 규칙 덕분에 편집 모달에서 드롭다운을
     * "연결 안 함"으로 바꾸고 저장하면 코어 저장 한 번으로 해제까지 처리된다.
     *
     * @param  string  $notificationType  코어 notification_definitions.type
     * @param  string|null  $templateCode  연결할 카카오 템플릿 코드 (빈 값=해제)
     * @param  string|null  $templateName  템플릿 이름 스냅샷 (고아 감지용)
     * @param  bool  $fallbackSmsEnabled  실패 시 SMS 대체발송 여부
     */
    public function applyFromTemplateSave(
        string $notificationType,
        ?string $templateCode,
        ?string $templateName,
        bool $fallbackSmsEnabled,
    ): void {
        $code = trim((string) $templateCode);

        if ($code === '') {
            $this->unbind($notificationType);

            return;
        }

        $this->bind($notificationType, [
            'template_code' => $code,
            'template_name' => trim((string) $templateName),
            'fallback_sms_enabled' => $fallbackSmsEnabled,
        ]);
    }
}
