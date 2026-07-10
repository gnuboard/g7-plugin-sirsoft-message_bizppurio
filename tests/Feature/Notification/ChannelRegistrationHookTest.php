<?php

namespace Plugins\Sirsoft\MessageBizppurio\Tests\Feature\Notification;

use App\Extension\HookManager;
use App\Services\ChannelReadinessService;
use App\Services\NotificationChannelService;
use App\Services\PluginSettingsService;
use Plugins\Sirsoft\MessageBizppurio\Listeners\RegisterNotificationChannelsListener;
use Plugins\Sirsoft\MessageBizppurio\Tests\PluginTestCase;

/**
 * 채널 등록 리스너를 실제 훅 체인으로 검증한다.
 *
 * 리스너 메서드를 직접 부르지 않고, HookManager 에 필터를 등록한 뒤 코어 서비스가
 * 발화하는 훅을 통해 sms/alimtalk 채널이 노출되고 readiness 가 해석되는지 관찰한다.
 * (Hook/Event 도메인 규정: 실제 훅 체인으로 관찰 가능한 상태 변화 검증)
 */
class ChannelRegistrationHookTest extends PluginTestCase
{
    /**
     * 리스너를 실제 훅 파이프라인에 등록합니다.
     *
     * @param  array<string, string>  $settings  플러그인 설정 stub
     */
    private function registerListener(array $settings = []): void
    {
        $stub = new class($settings) extends PluginSettingsService
        {
            /** @param array<string, string> $map */
            public function __construct(private array $map) {}

            public function get(string $identifier, ?string $key = null, mixed $default = null): mixed
            {
                return $this->map[$key] ?? $default;
            }
        };

        $listener = new RegisterNotificationChannelsListener($stub);

        HookManager::addFilter(
            'core.notification.filter_available_channels',
            fn ($channels) => $listener->addChannels($channels),
            20,
        );
        HookManager::addFilter(
            'core.notification.channel_readiness',
            fn ($result, $channelId) => $listener->checkReadiness($result, $channelId),
            20,
        );
    }

    public function test_코어_채널서비스가_sms_alimtalk을_노출한다(): void
    {
        $this->registerListener();

        // 실제 코어 서비스가 filter_available_channels 훅을 발화한다
        $channels = app(NotificationChannelService::class)->getAvailableChannels();
        $ids = array_column($channels, 'id');

        $this->assertContains('sms', $ids);
        $this->assertContains('alimtalk', $ids);
    }

    public function test_코어_channelservice가_비회원_허용을_인식한다(): void
    {
        $this->registerListener();

        $service = app(NotificationChannelService::class);

        $this->assertTrue($service->isChannelGuestAllowed('sms'), 'sms 는 allow_guest:true 여야 한다.');
        $this->assertTrue($service->isChannelGuestAllowed('alimtalk'));
    }

    public function test_코어_readiness서비스가_미설정_sms를_not_ready로_판정한다(): void
    {
        $this->registerListener([]); // 설정 비어 있음

        $result = app(ChannelReadinessService::class)->check('sms');

        $this->assertFalse($result['ready']);
        $this->assertStringContainsString('readiness.sms_credentials_missing', (string) $result['reason']);
    }

    public function test_코어_readiness서비스가_완비_sms를_ready로_판정한다(): void
    {
        $this->registerListener([
            'bizppurio_id' => 'acme',
            'password' => 'secret',
            'sender_number' => '025550000',
        ]);

        $result = app(ChannelReadinessService::class)->check('sms');

        $this->assertTrue($result['ready']);
    }
}
