<?php

declare(strict_types=1);

namespace Plugins\Sirsoft\MessageBizppurio\Services;

use App\ActivityLog\Traits\ResolvesActivityLogType;
use App\Services\PluginSettingsService;
use Plugins\Sirsoft\MessageBizppurio\Exceptions\BizppurioApiException;

/**
 * 알림톡 템플릿 관리 서비스 (Phase 5).
 *
 * 카카오 관리 API(kapi.ppurio.com)를 BizppurioKakaoApiClient 로 위임하여 알림톡 템플릿의
 * 실시간 조회·등록·수정·삭제·검수(요청/취소)·상태변경(중지/해제/승인취소)을 수행한다.
 * 템플릿은 DB 에 저장하지 않고 매 요청 실시간으로 조회한다(계획서 §6-3).
 *
 * 이 서비스는 다음 두 가지 도메인 로직을 담당한다.
 * - serviceStatus(REG/REQ/REJ/RDY/ACT/DMT/STP/BLK) → 상태 배지 + 상태별 가능 액션 매핑
 * - templateMessageType(BA/EX/AD/MI) 백엔드 자동 계산(부가정보·채널추가 버튼 유무로 결정)
 *
 * 발신프로필 키(senderKey)는 환경설정(sender_key)에서 가져오며, 미설정 시 조회 자체가
 * 불가능하므로 화면은 readiness 로 사전 안내한다(§6-3).
 */
class AlimtalkTemplateService
{
    use ResolvesActivityLogType;

    /** 플러그인 식별자 (manifest 와 일치) */
    private const PLUGIN_IDENTIFIER = 'sirsoft-message_bizppurio';

    /** 목록 조회 기본 페이지 크기 */
    private const DEFAULT_COUNT = 20;

    /**
     * serviceStatus → 상태 배지 매핑.
     *
     * key = kapi serviceStatus, value = ['label_key' => lang key, 'variant' => 배지 색].
     * variant 는 프론트가 배지 색상 클래스로 사용한다(green/yellow/red/gray/dark/purple).
     *
     * @var array<string, array{label_key: string, variant: string}>
     */
    private const STATUS_BADGES = [
        'RDY' => ['label_key' => 'sendable', 'variant' => 'green'],
        'ACT' => ['label_key' => 'sendable', 'variant' => 'green'],
        'REQ' => ['label_key' => 'inspecting', 'variant' => 'yellow'],
        'REJ' => ['label_key' => 'rejected', 'variant' => 'red'],
        'REG' => ['label_key' => 'uninspected', 'variant' => 'gray'],
        'STP' => ['label_key' => 'stopped', 'variant' => 'dark'],
        'BLK' => ['label_key' => 'blocked', 'variant' => 'dark'],
        'DMT' => ['label_key' => 'dormant', 'variant' => 'purple'],
    ];

    /**
     * serviceStatus → 상태별 가능 액션(정본 표, §6-3).
     *
     * @var array<string, array<int, string>>
     */
    private const STATUS_ACTIONS = [
        'REG' => ['edit', 'delete', 'request'],
        'REQ' => ['cancel_request'],
        'REJ' => ['edit', 'delete'],
        'RDY' => ['stop', 'cancel_approval'],
        'ACT' => ['stop', 'cancel_approval'],
        'STP' => ['reuse'],
        'DMT' => ['release'],
        'BLK' => [],
    ];

    /**
     * @param  BizppurioKakaoApiClient  $kakao  카카오 관리 API 클라이언트
     * @param  PluginSettingsService  $pluginSettings  환경설정 조회(sender_key)
     */
    public function __construct(
        private readonly BizppurioKakaoApiClient $kakao,
        private readonly PluginSettingsService $pluginSettings,
    ) {}

    /**
     * 알림톡 템플릿 목록을 실시간 조회합니다.
     *
     * @param  array<string, mixed>  $filters  status(templateStatus)·keyword·page·count
     * @return array{templates: array<int, array<string, mixed>>, pagination: array<string, int>}
     *
     * @throws BizppurioApiException 자격증명 미설정·조회 실패 시
     */
    public function list(array $filters = []): array
    {
        $params = [
            'count' => (int) ($filters['count'] ?? self::DEFAULT_COUNT),
            'page' => max(1, (int) ($filters['page'] ?? 1)),
        ];

        if (! empty($filters['status'])) {
            $params['templateStatus'] = (string) $filters['status'];
        }

        if (! empty($filters['keyword'])) {
            $params['keyword'] = (string) $filters['keyword'];
        }

        $response = $this->kakao->getTemplateList($this->senderKey(), $params);

        $this->assertSuccess($response);

        $rows = (array) ($response['data']['list'] ?? $response['data'] ?? []);

        return [
            'templates' => array_map(fn (array $row) => $this->decorate($row), $rows),
            'pagination' => [
                'total' => (int) ($response['totalCount'] ?? count($rows)),
                'total_page' => (int) ($response['totalPage'] ?? 1),
                'current_page' => (int) ($response['currentPage'] ?? $params['page']),
                'per_page' => $params['count'],
            ],
        ];
    }

    /**
     * 알림톡 템플릿 상세를 실시간 조회합니다.
     *
     * @param  string  $templateCode  템플릿 코드
     * @return array<string, mixed> 배지·가능 액션이 부가된 템플릿 상세
     *
     * @throws BizppurioApiException 자격증명 미설정·조회 실패 시
     */
    public function detail(string $templateCode): array
    {
        $response = $this->kakao->getTemplateDetail($this->senderKey(), $templateCode);

        $this->assertSuccess($response);

        return $this->decorate((array) ($response['data'] ?? []));
    }

    /**
     * 템플릿 등록에 사용할 카테고리 목록 전체를 조회합니다.
     *
     * @return array<int, array<string, mixed>> [{code, name, groupName}]
     *
     * @throws BizppurioApiException 자격증명 미설정·조회 실패 시
     */
    public function categories(): array
    {
        $response = $this->kakao->request('/v3/kakao/template/category/all');

        $this->assertSuccess($response);

        return array_values((array) ($response['data'] ?? []));
    }

    /**
     * 발신프로필(사용중) 정보를 조회합니다.
     *
     * @return array<string, mixed> 발신프로필 응답 data
     *
     * @throws BizppurioApiException 자격증명 미설정·조회 실패 시
     */
    public function senderProfiles(): array
    {
        $response = $this->kakao->getSenderProfiles();

        $this->assertSuccess($response);

        return (array) ($response['data'] ?? []);
    }

    /**
     * 이미지형 템플릿용 이미지를 카카오 서버에 업로드하고 등록 URL 을 반환합니다.
     *
     * 업로드 성공 시 카카오가 반환한 이미지 URL 을 `templateImageUrl` 로 사용한다.
     *
     * @param  string  $filePath  임시 업로드 파일 절대 경로
     * @param  string  $fileName  원본 파일명
     * @return string 카카오 서버에 등록된 이미지 URL
     *
     * @throws BizppurioApiException 업로드 실패 시
     */
    public function uploadImage(string $filePath, string $fileName): string
    {
        $response = $this->kakao->uploadTemplateImage($filePath, $fileName);

        $this->assertSuccess($response);

        return (string) ($response['image'] ?? '');
    }

    /**
     * 알림톡 템플릿을 신규 등록합니다.
     *
     * senderKey·templateMessageType 은 서비스가 채우며(FormRequest 는 화면 입력만 검증),
     * messageType 은 부가정보/채널추가 버튼 유무로 자동 계산한다.
     *
     * @param  array<string, mixed>  $data  검증된 템플릿 입력
     * @return array<string, mixed> 등록 결과(템플릿 상세)
     *
     * @throws BizppurioApiException 등록 실패 시
     */
    public function create(array $data): array
    {
        $payload = $this->buildPayload($data);

        $response = $this->kakao->request('/v3/kakao/template/add', $payload);

        $this->assertSuccess($response);

        $this->logActivity('sirsoft-message_bizppurio.alimtalk_template.create', [
            'description_key' => 'sirsoft-message_bizppurio::activity_log.description.alimtalk_template_create',
            'description_params' => ['template_name' => (string) ($data['templateName'] ?? '')],
        ]);

        return (array) ($response['data'] ?? []);
    }

    /**
     * 알림톡 템플릿을 수정합니다. (대기(R) + 검수 REG/REJ 상태만 가능)
     *
     * @param  string  $templateCode  기존 템플릿 코드
     * @param  array<string, mixed>  $data  검증된 템플릿 입력
     * @return array<string, mixed> 수정 결과(템플릿 상세)
     *
     * @throws BizppurioApiException 수정 실패 시
     */
    public function update(string $templateCode, array $data): array
    {
        $payload = array_merge($this->buildPayload($data), [
            'templateCode' => $templateCode,
        ]);

        $response = $this->kakao->request('/v3/kakao/template/update', $payload);

        $this->assertSuccess($response);

        $this->logActivity('sirsoft-message_bizppurio.alimtalk_template.update', [
            'description_key' => 'sirsoft-message_bizppurio::activity_log.description.alimtalk_template_update',
            'description_params' => ['template_code' => $templateCode],
        ]);

        return (array) ($response['data'] ?? []);
    }

    /**
     * 알림톡 템플릿을 삭제합니다. (대기(R) + 검수 REG/REJ 상태만 가능)
     *
     * @param  string  $templateCode  템플릿 코드
     *
     * @throws BizppurioApiException 삭제 실패 시
     */
    public function delete(string $templateCode): void
    {
        $this->simpleAction('/v3/kakao/template/delete', $templateCode, 'delete');
    }

    /**
     * 검수를 요청합니다. (대기(R) + 검수 REG 상태만 가능)
     *
     * @param  string  $templateCode  템플릿 코드
     * @param  string|null  $comment  의견/문의(최대 500자)
     *
     * @throws BizppurioApiException 요청 실패 시
     */
    public function requestInspection(string $templateCode, ?string $comment = null): void
    {
        $params = [
            'senderKey' => $this->senderKey(),
            'templateCode' => $templateCode,
        ];

        if ($comment !== null && $comment !== '') {
            $params['comment'] = $comment;
        }

        $response = $this->kakao->request('/v3/kakao/template/request', $params);

        $this->assertSuccess($response);

        $this->logActivity('sirsoft-message_bizppurio.alimtalk_template.request', [
            'description_key' => 'sirsoft-message_bizppurio::activity_log.description.alimtalk_template_request',
            'description_params' => ['template_code' => $templateCode],
        ]);
    }

    /**
     * 검수 요청을 취소합니다. (검수 REQ 상태만 가능)
     *
     * @param  string  $templateCode  템플릿 코드
     *
     * @throws BizppurioApiException 취소 실패 시
     */
    public function cancelRequest(string $templateCode): void
    {
        $this->simpleAction('/v3/kakao/template/cancel_request', $templateCode, 'cancel_request');
    }

    /**
     * 승인된 템플릿을 중지합니다. (RDY/ACT 상태)
     *
     * @param  string  $templateCode  템플릿 코드
     *
     * @throws BizppurioApiException 중지 실패 시
     */
    public function stop(string $templateCode): void
    {
        $this->simpleAction('/v3/kakao/template/stop', $templateCode, 'stop');
    }

    /**
     * 중지된 템플릿을 정상으로 되돌립니다. (STP 상태)
     *
     * @param  string  $templateCode  템플릿 코드
     *
     * @throws BizppurioApiException 해제 실패 시
     */
    public function reuse(string $templateCode): void
    {
        $this->simpleAction('/v3/kakao/template/reuse', $templateCode, 'reuse');
    }

    /**
     * 승인을 취소합니다(재검수 요청 가능 상태로 복귀). (RDY/ACT 상태)
     *
     * @param  string  $templateCode  템플릿 코드
     *
     * @throws BizppurioApiException 취소 실패 시
     */
    public function cancelApproval(string $templateCode): void
    {
        $this->simpleAction('/v3/kakao/template/cancel_approval', $templateCode, 'cancel_approval');
    }

    /**
     * 휴면 템플릿을 해제합니다. (DMT 상태)
     *
     * @param  string  $templateCode  템플릿 코드
     *
     * @throws BizppurioApiException 해제 실패 시
     */
    public function release(string $templateCode): void
    {
        $this->simpleAction('/v3/kakao/template/release', $templateCode, 'release');
    }

    /**
     * senderKey + templateCode 만 필요한 단순 상태 변경 액션의 공통 처리.
     *
     * @param  string  $path  kapi 엔드포인트 경로
     * @param  string  $templateCode  템플릿 코드
     * @param  string  $action  활동 로그 action 세그먼트
     *
     * @throws BizppurioApiException 요청 실패 시
     */
    private function simpleAction(string $path, string $templateCode, string $action): void
    {
        $response = $this->kakao->request($path, [
            'senderKey' => $this->senderKey(),
            'templateCode' => $templateCode,
        ]);

        $this->assertSuccess($response);

        $this->logActivity("sirsoft-message_bizppurio.alimtalk_template.{$action}", [
            'description_key' => "sirsoft-message_bizppurio::activity_log.description.alimtalk_template_{$action}",
            'description_params' => ['template_code' => $templateCode],
        ]);
    }

    /**
     * 화면 입력을 kapi 등록/수정 payload 로 조립합니다.
     *
     * - senderKey 는 환경설정에서 주입
     * - templateMessageType 은 부가정보·채널추가(AC) 버튼 유무로 자동 계산
     * - 빈 선택항목은 payload 에서 제외(kapi 유형별 필수 규칙 위반 방지)
     *
     * @param  array<string, mixed>  $data  검증된 입력
     * @return array<string, mixed> kapi payload (bizId/apiKey 제외 — 클라이언트가 주입)
     */
    private function buildPayload(array $data): array
    {
        $payload = [
            'senderKey' => $this->senderKey(),
            'senderKeyType' => 'S',
            'templateName' => (string) $data['templateName'],
            'templateContent' => (string) $data['templateContent'],
            'categoryCode' => (string) $data['categoryCode'],
            'templateEmphasizeType' => (string) ($data['templateEmphasizeType'] ?? 'NONE'),
        ];

        if (! empty($data['templateCode'])) {
            $payload['templateCode'] = (string) $data['templateCode'];
        }

        // 강조표기형(TEXT)
        if ($payload['templateEmphasizeType'] === 'TEXT') {
            $payload['templateTitle'] = (string) ($data['templateTitle'] ?? '');
            $payload['templateSubtitle'] = (string) ($data['templateSubtitle'] ?? '');
        }

        // 이미지형(IMAGE)
        if ($payload['templateEmphasizeType'] === 'IMAGE') {
            $payload['templateImageName'] = (string) ($data['templateImageName'] ?? '');
            $payload['templateImageUrl'] = (string) ($data['templateImageUrl'] ?? '');
        }

        // 선택항목(§6-4): 부가정보 · 미리보기 메시지
        foreach (['templatePreviewMessage', 'templateExtra'] as $optional) {
            if (! empty($data[$optional])) {
                $payload[$optional] = (string) $data[$optional];
            }
        }

        if (array_key_exists('securityFlag', $data)) {
            $payload['securityFlag'] = (bool) $data['securityFlag'];
        }

        // 선택항목: 버튼(최대 5) · 바로연결(최대 10)
        $buttons = $this->normalizeList($data['buttons'] ?? []);
        if ($buttons !== []) {
            $payload['buttons'] = $buttons;
        }

        $quickReplies = $this->normalizeList($data['quickReplies'] ?? []);
        if ($quickReplies !== []) {
            $payload['quickReplies'] = $quickReplies;
        }

        // 선택항목: 대표 링크(빈 링크 필드 제거 후 하나라도 있으면 포함)
        $representLink = array_filter(
            (array) ($data['templateRepresentLink'] ?? []),
            static fn ($v) => is_string($v) && $v !== '',
        );
        if ($representLink !== []) {
            $payload['templateRepresentLink'] = $representLink;
        }

        $payload['templateMessageType'] = $this->resolveMessageType($payload);

        return $payload;
    }

    /**
     * templateMessageType 을 부가정보·채널추가(AC) 버튼 유무로 계산합니다.
     *
     * - 부가정보(templateExtra) 있음 → EX
     * - 채널추가(AC) 버튼 있음 → AD
     * - 둘 다 → MI
     * - 없음 → BA
     *
     * @param  array<string, mixed>  $payload  조립 중인 payload
     * @return string BA/EX/AD/MI
     */
    private function resolveMessageType(array $payload): string
    {
        $hasExtra = ! empty($payload['templateExtra']);
        $hasAddChannel = false;

        foreach ((array) ($payload['buttons'] ?? []) as $button) {
            if (($button['linkType'] ?? null) === 'AC') {
                $hasAddChannel = true;
                break;
            }
        }

        return match (true) {
            $hasExtra && $hasAddChannel => 'MI',
            $hasExtra => 'EX',
            $hasAddChannel => 'AD',
            default => 'BA',
        };
    }

    /**
     * 버튼/바로연결 배열에서 빈 항목을 제거하고 정규화합니다.
     *
     * @param  mixed  $list  입력 배열
     * @return array<int, array<string, mixed>>
     */
    private function normalizeList(mixed $list): array
    {
        if (! is_array($list)) {
            return [];
        }

        return array_values(array_filter($list, static fn ($item) => is_array($item) && ! empty($item['name']) && ! empty($item['linkType'])));
    }

    /**
     * 템플릿 행에 상태 배지·가능 액션을 부가합니다.
     *
     * serviceStatus(목록) 또는 inspectionStatus/status(상세)에서 배지 기준 상태를 도출한다.
     *
     * @param  array<string, mixed>  $row  kapi 템플릿 행
     * @return array<string, mixed> 배지·액션이 부가된 행
     */
    private function decorate(array $row): array
    {
        $status = (string) ($row['serviceStatus'] ?? $this->deriveStatus($row));
        $badge = self::STATUS_BADGES[$status] ?? ['label_key' => 'unknown', 'variant' => 'gray'];

        $row['service_status'] = $status;
        $row['status_badge'] = [
            'label_key' => 'sirsoft-message_bizppurio::messages.template.status.'.$badge['label_key'],
            'variant' => $badge['variant'],
        ];
        $row['available_actions'] = self::STATUS_ACTIONS[$status] ?? [];

        return $row;
    }

    /**
     * 상세 응답에서 serviceStatus 가 없을 때 status/inspectionStatus 로 상태를 추정합니다.
     *
     * 상세 조회는 serviceStatus 대신 status(S/A/R)+inspectionStatus(REG/REQ/REJ/APR)를
     * 내려주므로, 목록과 동일한 배지 체계로 환원한다.
     *
     * @param  array<string, mixed>  $row  kapi 템플릿 상세 행
     * @return string serviceStatus 코드
     */
    private function deriveStatus(array $row): string
    {
        $inspection = (string) ($row['inspectionStatus'] ?? '');
        $status = (string) ($row['status'] ?? '');
        $block = (bool) ($row['block'] ?? false);
        $dormant = (bool) ($row['dormant'] ?? false);

        return match (true) {
            $block => 'BLK',
            $dormant => 'DMT',
            $inspection === 'REQ' => 'REQ',
            $inspection === 'REJ' => 'REJ',
            $inspection === 'APR' && $status === 'S' => 'STP',
            $inspection === 'APR' && $status === 'A' => 'ACT',
            $inspection === 'APR' => 'RDY',
            default => 'REG',
        };
    }

    /**
     * 환경설정에서 발신프로필 키(sender_key)를 조회합니다.
     *
     * @return string 발신프로필 키
     *
     * @throws BizppurioApiException 미설정 시
     */
    private function senderKey(): string
    {
        $settings = $this->pluginSettings->get(self::PLUGIN_IDENTIFIER) ?? [];
        $senderKey = (string) ($settings['sender_key'] ?? '');

        if ($senderKey === '') {
            throw new BizppurioApiException(
                __('sirsoft-message_bizppurio::messages.error.sender_key_missing'),
            );
        }

        return $senderKey;
    }

    /**
     * kapi 응답이 성공(200)이 아니면 message 를 담아 예외를 던집니다.
     *
     * @param  array<string, mixed>  $response  kapi 응답
     *
     * @throws BizppurioApiException 실패 코드 시
     */
    private function assertSuccess(array $response): void
    {
        if ($this->kakao->isSuccess($response)) {
            return;
        }

        $message = (string) ($response['message'] ?? '');
        $code = (string) ($response['code'] ?? '');

        throw new BizppurioApiException(
            $message !== ''
                ? $message
                : __('sirsoft-message_bizppurio::messages.error.kakao_request_failed'),
            resultCode: $code !== '' ? $code : null,
        );
    }
}
