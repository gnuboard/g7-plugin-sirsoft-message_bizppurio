<?php

declare(strict_types=1);

namespace Plugins\Sirsoft\MessageBizppurio\Listeners;

use App\Contracts\Extension\HookListenerInterface;
use App\Services\PluginSettingsService;

/**
 * 비즈뿌리오 문자·알림톡 채널을 코어 알림 시스템에 등록하는 필터 리스너.
 *
 * 코어를 수정하지 않고 다음 4계열 필터 훅을 구독해 sms·alimtalk 채널을 노출·게이트한다.
 *
 * 1) core.notification.filter_available_channels
 *    - 알림 설정 화면의 채널 탭 SSoT. sms·alimtalk 메타(name_key/allow_guest 등)를 추가한다.
 * 2) core.notification.channel_readiness
 *    - 채널별 환경설정 충족 여부({ready, reason})를 반환한다(D2).
 * 3) {prefix}.notification.channels  (core.auth / sirsoft-ecommerce / sirsoft-board)
 *    - 레거시 다채널 자동 결정 경로에서 정의별 채널 후보에 sms·alimtalk 을 더한다(D7 3영역).
 *
 * 실제 발송 위임({prefix}.notification.to_sms/to_alimtalk)은 채널 드라이버가
 * ChannelManager 에 등록되어야 발화하므로, 드라이버 등록은 ServiceProvider::boot() 가
 * 담당한다(이 리스너는 채널 "노출·판정"만 책임진다).
 *
 * alimtalk 의 uses_custom_list 플래그(알림톡 탭 배타 전환)는 탭 UI 주입(§6-2)과 강결합이라
 * Phase 6 에서 이 리스너에 추가한다. Phase 3 에서는 채널 메타·readiness·발송 위임 골격만 둔다.
 */
class RegisterNotificationChannelsListener implements HookListenerInterface
{
    /** 플러그인 식별자 (manifest 와 일치) */
    private const PLUGIN_IDENTIFIER = 'sirsoft-message_bizppurio';

    /** lang 네임스페이스 접두사 (plugin.php 와 동일) */
    private const LANG = 'sirsoft-message_bizppurio::messages';

    /** 이 플러그인이 등록하는 채널 ID 목록 */
    private const CHANNEL_IDS = ['sms', 'alimtalk'];

    /** 3영역 채널 후보 확장을 구독할 hookPrefix (D7) */
    private const CHANNEL_HOOK_PREFIXES = ['core.auth', 'sirsoft-ecommerce', 'sirsoft-board'];

    /**
     * @param  PluginSettingsService  $pluginSettings  readiness 검사를 위한 환경설정 조회
     */
    public function __construct(
        private readonly PluginSettingsService $pluginSettings,
    ) {}

    /**
     * 구독할 훅 목록 반환.
     *
     * filter_available_channels / channel_readiness 는 고정 훅이고,
     * {prefix}.notification.channels 는 3영역 prefix 별로 동적으로 펼쳐 등록한다.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getSubscribedHooks(): array
    {
        $hooks = [
            'core.notification.filter_available_channels' => [
                'method' => 'addChannels',
                'priority' => 20,
                'type' => 'filter',
            ],
            'core.notification.channel_readiness' => [
                'method' => 'checkReadiness',
                'priority' => 20,
                'type' => 'filter',
            ],
        ];

        foreach (self::CHANNEL_HOOK_PREFIXES as $prefix) {
            $hooks["{$prefix}.notification.channels"] = [
                'method' => 'addChannelCandidates',
                'priority' => 20,
                'type' => 'filter',
            ];
        }

        return $hooks;
    }

    /**
     * 기본 핸들러 (미사용 — 필터 메서드로 처리).
     *
     * @param  mixed  ...$args
     */
    public function handle(...$args): void {}

    /**
     * 사용 가능한 채널 목록에 sms·alimtalk 메타를 추가합니다.
     *
     * 코어 NotificationChannelService::getAvailableChannels() 가 name_key/description_key/
     * source_label_key 를 활성 locale 로 해석하므로(localized_payload), lang key 로 선언한다.
     * 중복 방지: 이미 존재하는 id 는 다시 추가하지 않는다.
     *
     * allow_guest:true — 비회원 문자 발송 허용(D1). 실제 게스트 전화번호는 SmsChannelDriver 가
     * data 에서 해석한다.
     *
     * @param  array<int, array<string, mixed>>  $channels  현재 채널 메타 목록
     * @return array<int, array<string, mixed>> sms·alimtalk 이 추가된 목록
     */
    public function addChannels(array $channels): array
    {
        $existingIds = array_column($channels, 'id');

        foreach ($this->channelMetas() as $meta) {
            if (! in_array($meta['id'], $existingIds, true)) {
                $channels[] = $meta;
            }
        }

        return $channels;
    }

    /**
     * 채널 준비 상태를 검사합니다 (core.notification.channel_readiness).
     *
     * 코어/타 플러그인 채널의 판정({ready,reason})은 그대로 통과시키고, 우리 채널(sms/alimtalk)
     * 일 때만 환경설정 충족 여부로 교체한다(D2).
     *
     * - sms: bizppurio_id + password + sender_number
     * - alimtalk: sms 조건 + api_key + sender_key
     *
     * @param  array{ready: bool, reason: string|null}  $result  이전 필터까지의 판정
     * @param  string  $channelId  검사 대상 채널
     * @return array{ready: bool, reason: string|null}
     */
    public function checkReadiness(array $result, string $channelId): array
    {
        return match ($channelId) {
            'sms' => $this->checkSmsReadiness(),
            'alimtalk' => $this->checkAlimtalkReadiness(),
            default => $result,
        };
    }

    /**
     * 레거시 다채널 자동 결정 경로에서 채널 후보에 sms·alimtalk 을 더합니다.
     *
     * {prefix}.notification.channels 는 채널 미지정(다채널) 발송 경로에서만 발화하며(코어 확인),
     * 채널 지정 경로에서는 filter_available_channels 가 SSoT 다. 두 경로 모두에서 채널이
     * 누락되지 않도록 후보에 우리 채널 id 를 더한다(중복 제거).
     *
     * @param  array<int, string>  $channels  정의별 채널 id 후보
     * @param  string  $type  알림 정의 유형 (미사용)
     * @param  object|null  $notifiable  수신자 (미사용)
     * @return array<int, string> 우리 채널이 더해진 후보
     */
    public function addChannelCandidates(array $channels, string $type = '', ?object $notifiable = null): array
    {
        foreach (self::CHANNEL_IDS as $id) {
            if (! in_array($id, $channels, true)) {
                $channels[] = $id;
            }
        }

        return array_values($channels);
    }

    /**
     * sms·alimtalk 채널 메타 정의를 반환합니다.
     *
     * @return array<int, array<string, mixed>>
     */
    private function channelMetas(): array
    {
        return [
            [
                'id' => 'sms',
                'name_key' => self::LANG.'.channels.sms.name',
                'description_key' => self::LANG.'.channels.sms.description',
                'icon' => 'fas fa-comment-sms',
                'source' => self::PLUGIN_IDENTIFIER,
                'source_label_key' => self::LANG.'.channels.source_label',
                'allow_guest' => true,
            ],
            [
                'id' => 'alimtalk',
                'name_key' => self::LANG.'.channels.alimtalk.name',
                'description_key' => self::LANG.'.channels.alimtalk.description',
                'icon' => 'fas fa-comment-dots',
                'source' => self::PLUGIN_IDENTIFIER,
                'source_label_key' => self::LANG.'.channels.source_label',
                'allow_guest' => true,
            ],
        ];
    }

    /**
     * SMS 채널 준비 상태를 검사합니다.
     *
     * @return array{ready: bool, reason: string|null}
     */
    private function checkSmsReadiness(): array
    {
        if ($this->missing('bizppurio_id') || $this->missing('password')) {
            return $this->notReady('sms_credentials_missing');
        }

        if ($this->missing('sender_number')) {
            return $this->notReady('sms_sender_number_missing');
        }

        return ['ready' => true, 'reason' => null];
    }

    /**
     * 알림톡 채널 준비 상태를 검사합니다.
     *
     * @return array{ready: bool, reason: string|null}
     */
    private function checkAlimtalkReadiness(): array
    {
        if ($this->missing('bizppurio_id') || $this->missing('password')) {
            return $this->notReady('sms_credentials_missing');
        }

        if ($this->missing('sender_number')) {
            return $this->notReady('sms_sender_number_missing');
        }

        if ($this->missing('api_key')) {
            return $this->notReady('alimtalk_api_key_missing');
        }

        if ($this->missing('sender_key')) {
            return $this->notReady('alimtalk_sender_key_missing');
        }

        return ['ready' => true, 'reason' => null];
    }

    /**
     * 환경설정 값이 비어 있는지 확인합니다.
     *
     * @param  string  $key  설정 키
     * @return bool 비어 있으면 true
     */
    private function missing(string $key): bool
    {
        $value = $this->pluginSettings->get(self::PLUGIN_IDENTIFIER, $key, '');

        return trim((string) $value) === '';
    }

    /**
     * ready=false 판정을 lang reason key 와 함께 반환합니다.
     *
     * @param  string  $reasonKey  messages.readiness.* 하위 키
     * @return array{ready: bool, reason: string}
     */
    private function notReady(string $reasonKey): array
    {
        return ['ready' => false, 'reason' => self::LANG.'.readiness.'.$reasonKey];
    }
}
