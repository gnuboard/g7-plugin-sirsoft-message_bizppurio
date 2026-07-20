<?php

namespace Plugins\Sirsoft\MessageBizppurio;

use App\Enums\ExtensionOwnerType;
use App\Extension\AbstractPlugin;
use App\Extension\Helpers\ExtensionMenuSyncHelper;
use App\Extension\ModuleManager;
use Database\Seeders\NotificationDefinitionSeeder;
use Illuminate\Support\Facades\Log;
use Plugins\Sirsoft\MessageBizppurio\Listeners\GuestPhoneExtractListener;
use Plugins\Sirsoft\MessageBizppurio\Listeners\LinkNotificationLogListener;
use Plugins\Sirsoft\MessageBizppurio\Listeners\RegisterNotificationChannelsListener;
use Plugins\Sirsoft\MessageBizppurio\Listeners\SeedChannelTemplatesListener;
use Plugins\Sirsoft\MessageBizppurio\Listeners\ValidateBizppurioSettingsListener;

/**
 * 비즈뿌리오 메시지 발송 플러그인
 *
 * 비즈뿌리오 연동 SMS/LMS·카카오 알림톡 발송을 제공합니다.
 * 코어 알림 시스템의 채널로 문자·알림톡을 발송하고, 발송 결과를 webhook 으로
 * 수신하여 이력에 기록합니다.
 */
class Plugin extends AbstractPlugin
{
    /**
     * 플러그인 메타데이터 반환
     *
     * @return array 메타데이터
     */
    public function getMetadata(): array
    {
        return [
            'author' => 'Sirsoft',
            'license' => 'MIT',
            'homepage' => 'https://sir.kr',
            'keywords' => ['bizppurio', 'sms', 'lms', 'alimtalk', 'kakao', 'messaging', 'notification'],
        ];
    }

    /**
     * 플러그인 활성화 — 기존 회원 알림에 sms·alimtalk template 을 즉시 증강.
     *
     * SeedChannelTemplatesListener 는 코어/모듈이 알림 정의를 *시딩할 때* 필터 훅으로 증강한다.
     * 그러나 플러그인 활성화 시점에는 코어/모듈 정의가 이미 시딩돼 있어(우리 채널 없이) 다음
     * 재시딩까지 alimtalk template 이 생기지 않는다. 따라서 활성화 시 코어·활성 모듈의 알림
     * 정의를 재시드해, 이제 활성화된 우리 필터를 통과시켜 기존 정의에도 채널을 즉시 반영한다.
     *
     * 재시드는 모두 user_overrides 보존 upsert(멱등)이며 코어·모듈 파일을 수정하지 않는다.
     * 실패는 로그만 남기고 활성화 자체는 막지 않는다(발송·연동은 채널 등록만으로도 동작하며,
     * template 은 다음 정상 재시딩에도 수렴).
     *
     * @return bool 활성화 성공 여부
     */
    public function activate(): bool
    {
        try {
            app(NotificationDefinitionSeeder::class)->run();
            app(ModuleManager::class)->resyncAllActiveDeclarativeArtifacts();
        } catch (\Throwable $e) {
            Log::warning('[sirsoft-message_bizppurio] 알림 채널 template 증강 재시드 실패', [
                'error' => $e->getMessage(),
            ]);
        }

        return true;
    }

    /**
     * 플러그인 비활성화 — 이 플러그인 소속 관리자 메뉴 잔재 정리.
     *
     * 이 플러그인은 관리자 메뉴를 만들지 않는다(화면 배치 결정 2026-07-14 — 진입은 코어
     * 소유 설정 페이지 하나로 통일). 다만 이전 버전(getAdminMenus 사용 시기)에 생성된
     * 메뉴 row 가 DB 에 남아 있을 수 있어, 비활성화 시 currentSlugs=[] 로 cleanupStaleMenus
     * 를 호출해 잔재를 청소한다. 재활성화 시 아무 메뉴도 만들지 않으므로 정상 무메뉴 상태로
     * 수렴한다(멱등). 자식 메뉴 + role_menus 피벗은 helper 가 cascade 처리.
     *
     * @return bool 비활성화 성공 여부
     */
    public function deactivate(): bool
    {
        app(ExtensionMenuSyncHelper::class)->cleanupStaleMenus(
            ExtensionOwnerType::Plugin,
            $this->getIdentifier(),
            currentSlugs: [],
        );

        return true;
    }

    /**
     * 플러그인 제거 — 메뉴 잔재 안전망 (정상 흐름은 deactivate 가 먼저 처리).
     *
     * @return bool 제거 성공 여부
     */
    public function uninstall(): bool
    {
        $this->deactivate();

        return true;
    }

    /**
     * 플러그인 권한 목록 반환 (계층 구조)
     *
     * PluginManager 가 1레벨(플러그인 노드) → 2레벨(카테고리) → 3레벨(개별 권한) 트리로 등록.
     * 모든 권한은 admin 역할에 매핑.
     *
     * 권한 분할 의도:
     * - view: 발송 이력·알림톡 템플릿 조회 (모니터링)
     * - manage: 환경설정·템플릿 등록/검수·이벤트 연동 (운영)
     *
     * @return array 권한 정의 배열 (categories 계층 구조)
     */
    public function getPermissions(): array
    {
        return [
            'name' => [
                'ko' => '비즈뿌리오 메시지 발송',
                'en' => 'Bizppurio Messaging',
            ],
            'description' => [
                'ko' => '비즈뿌리오 메시지 발송 플러그인이 제공하는 권한',
                'en' => 'Permissions provided by the Bizppurio Messaging plugin',
            ],
            'categories' => [
                [
                    'identifier' => 'messaging',
                    'name' => ['ko' => '메시지 발송', 'en' => 'Messaging'],
                    'description' => [
                        'ko' => '메시지 발송 도메인 권한 (조회·관리)',
                        'en' => 'Messaging domain permissions (view, manage)',
                    ],
                    'permissions' => [
                        [
                            'action' => 'view',
                            'name' => ['ko' => '메시지 조회', 'en' => 'View Messaging'],
                            'description' => [
                                'ko' => '발송 이력·알림톡 템플릿 조회 (모니터링)',
                                'en' => 'View dispatch history and alimtalk templates (monitoring)',
                            ],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                        [
                            'action' => 'manage',
                            'name' => ['ko' => '메시지 관리', 'en' => 'Manage Messaging'],
                            'description' => [
                                'ko' => '환경설정·알림톡 템플릿 등록/검수·이벤트 연동 관리',
                                'en' => 'Manage settings, alimtalk template registration/inspection, and event bindings',
                            ],
                            'type' => 'admin',
                            'roles' => ['admin'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 플러그인 설정 스키마 반환
     *
     * 관리자 설정 페이지 UI 를 동적으로 생성하는 데 사용됩니다. 크리덴셜(비밀번호·API 키)은
     * sensitive 로 마킹하여 마스킹하며, frontend_schema(defaults.json)에서 expose:false 로
     * 프론트 노출을 차단합니다.
     *
     * 발송 시스템(account)과 카카오 관리 시스템(bizId)의 식별자는 동일한 '비즈뿌리오 아이디'
     * 이므로 bizppurio_id 단일 필드로 받는다.
     *
     * @return array 설정 스키마
     */
    public function getSettingsSchema(): array
    {
        return [
            'is_test_mode' => [
                'type' => 'boolean',
                'default' => true,
                'label' => ['ko' => '검수 모드', 'en' => 'Test Mode'],
                'hint' => [
                    'ko' => '검수 모드를 끄면 운영 환경으로 발송됩니다. 발송 API 도메인이 환경에 따라 분기됩니다.',
                    'en' => 'Turn off test mode to send in the production environment. The sending API domain differs by environment.',
                ],
                'required' => false,
            ],
            'bizppurio_id' => [
                'type' => 'string',
                'default' => '',
                'label' => ['ko' => '비즈뿌리오 아이디', 'en' => 'Bizppurio ID'],
                'hint' => [
                    'ko' => '발송·카카오 관리에 공통으로 사용하는 비즈뿌리오 아이디입니다.',
                    'en' => 'The Bizppurio account ID used for both sending and Kakao management.',
                ],
                'required' => false,
            ],
            'password' => [
                'type' => 'string',
                'default' => '',
                'label' => ['ko' => '비밀번호', 'en' => 'Password'],
                'hint' => [
                    'ko' => '발송 토큰 발급에 사용하는 비즈뿌리오 비밀번호입니다.',
                    'en' => 'The Bizppurio password used to issue the sending token.',
                ],
                'sensitive' => true,
                'required' => false,
            ],
            'api_key' => [
                'type' => 'string',
                'default' => '',
                'label' => ['ko' => 'API 키', 'en' => 'API Key'],
                'hint' => [
                    'ko' => '카카오 관리(알림톡 템플릿·발신프로필)에 사용하는 API 키입니다. 비즈뿌리오 고객센터로 아이디와 함께 접수하면 확인 후 발급됩니다.',
                    'en' => 'The API key used for Kakao management (alimtalk templates, sender profiles).',
                ],
                'sensitive' => true,
                'required' => false,
            ],
            'sender_number' => [
                'type' => 'string',
                'default' => '',
                'label' => ['ko' => '발신번호', 'en' => 'Sender Number'],
                'hint' => [
                    'ko' => '문자·알림톡 발송에 사용하는 발신 전화번호입니다.',
                    'en' => 'The sender phone number used for SMS and alimtalk delivery.',
                ],
                'required' => false,
            ],
            'sender_key' => [
                'type' => 'string',
                'default' => '',
                'label' => ['ko' => '알림톡 발신프로필 키', 'en' => 'Alimtalk Sender Profile Key'],
                'hint' => [
                    'ko' => '알림톡 발송·템플릿 조회에 사용하는 발신프로필 키(40자)입니다.',
                    'en' => 'The 40-character sender profile key used for alimtalk delivery and template lookup.',
                ],
                'sensitive' => true,
                'required' => false,
            ],
        ];
    }

    /**
     * 플러그인 설정 기본값 반환 (하위 호환)
     *
     * 신규 설치 기본값은 config/settings/defaults.json 의 defaults 섹션이 1순위이며,
     * 본 메서드는 하위 호환 경로로 동일 값을 반환한다.
     *
     * @return array 기본 설정값
     */
    public function getConfigValues(): array
    {
        return [
            'is_test_mode' => true,
            'bizppurio_id' => '',
            'password' => '',
            'api_key' => '',
            'sender_number' => '',
            'sender_key' => '',
        ];
    }

    /**
     * 이 플러그인이 등록할 알림 정의/템플릿 선언을 반환합니다.
     *
     * Phase 1 범위: 비즈뿌리오 지갑 잔액부족 시 관리자에게 보내는 자체 알림 1건.
     * (webhook 결과코드 9070/7436 감지 시 Phase 4 에서 이 알림을 발화)
     *
     * ※ 3영역(코어/게시판/이커머스) 알림톡 채널 기본 body 시드는 알림톡 채널 등록(Phase 3)·
     *   탭 연동(Phase 6)과 강결합이므로 Phase 6 으로 이관한다. 그때 본 메서드에 정의를 추가한다.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getNotificationDefinitions(): array
    {
        return [
            [
                'type' => 'bizppurio_balance_low',
                'hook_prefix' => 'sirsoft-message_bizppurio',
                'name' => [
                    'ko' => '비즈뿌리오 잔액 부족',
                    'en' => 'Bizppurio Balance Low',
                ],
                'description' => [
                    'ko' => '비즈뿌리오 지갑 잔액이 부족해 문자/알림톡 발송이 실패했을 때 관리자에게 발송',
                    'en' => 'Sent to admin when a message fails due to insufficient Bizppurio wallet balance',
                ],
                'channels' => ['mail', 'database'],
                'hooks' => ['sirsoft-message_bizppurio.balance.low'],
                'variables' => [
                    ['key' => 'name', 'description' => '수신자(관리자) 이름'],
                    ['key' => 'app_name', 'description' => '사이트 이름'],
                    ['key' => 'result_code', 'description' => '결과 코드(9070 문자 / 7436 알림톡)'],
                    ['key' => 'channel_label', 'description' => '발송 채널(문자/알림톡)'],
                    ['key' => 'settings_url', 'description' => '메시징 환경설정 URL'],
                    ['key' => 'site_url', 'description' => '사이트 URL'],
                ],
                'templates' => [
                    [
                        'channel' => 'mail',
                        'recipients' => [['type' => 'role', 'value' => 'admin']],
                        'subject' => [
                            'ko' => '[{app_name}] 비즈뿌리오 잔액이 부족합니다',
                            'en' => '[{app_name}] Bizppurio balance is insufficient',
                        ],
                        'body' => [
                            'ko' => '{name}님, 비즈뿌리오 지갑 잔액이 부족하여 {channel_label} 발송이 실패했습니다 (코드: {result_code}). 충전 후 발송이 정상화됩니다.',
                            'en' => 'Dear {name}, a {channel_label} message failed due to insufficient Bizppurio balance (code: {result_code}). Delivery resumes after recharging.',
                        ],
                    ],
                    [
                        'channel' => 'database',
                        'recipients' => [['type' => 'role', 'value' => 'admin']],
                        'subject' => [
                            'ko' => '비즈뿌리오 잔액 부족',
                            'en' => 'Bizppurio balance low',
                        ],
                        'body' => [
                            'ko' => '비즈뿌리오 잔액 부족으로 {channel_label} 발송이 실패했습니다 (코드: {result_code}).',
                            'en' => 'A {channel_label} message failed due to insufficient Bizppurio balance (code: {result_code}).',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * 훅 리스너 목록 반환
     *
     * - RegisterNotificationChannelsListener: 채널 등록/readiness/3영역 노출(Phase 3)
     * - SeedChannelTemplatesListener: 회원 알림에 sms·alimtalk template 증강(Phase 6 결정 D — 시딩 필터 훅)
     * - GuestPhoneExtractListener: 비회원 주문 전화번호 주입(Phase 3)
     * - LinkNotificationLogListener: 코어 알림 로그↔dispatch 연결(A-2 — 발송 이력 결과 주입 연결고리)
     * - ValidateBizppurioSettingsListener: 환경설정 검증
     *
     * @return array<class-string>
     */
    public function getHookListeners(): array
    {
        return [
            RegisterNotificationChannelsListener::class,
            SeedChannelTemplatesListener::class,
            GuestPhoneExtractListener::class,
            LinkNotificationLogListener::class,
            ValidateBizppurioSettingsListener::class,
        ];
    }
}
