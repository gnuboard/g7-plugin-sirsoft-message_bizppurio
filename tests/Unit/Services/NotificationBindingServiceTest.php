<?php

namespace Plugins\Sirsoft\MessageBizppurio\Tests\Unit\Services;

use Mockery;
use Plugins\Sirsoft\MessageBizppurio\Repositories\BizppurioNotificationBindingRepository;
use Plugins\Sirsoft\MessageBizppurio\Services\AlimtalkTemplateService;
use Plugins\Sirsoft\MessageBizppurio\Services\NotificationBindingService;
use Plugins\Sirsoft\MessageBizppurio\Tests\PluginTestCase;

/**
 * NotificationBindingService — 연동 조회(all·approvedTemplates)·저장(bind·unbind·applyFromTemplateSave) 검증.
 *
 * Phase 6 재설계 A: 알림톡 탭은 코어 기본 목록·편집 모달을 그대로 쓴다. 이 서비스는 코어 편집
 * 모달 전용 칸이 소비하는 조회(현재 연동 맵·승인 템플릿 드롭다운)와 코어 [저장] 훅이 호출하는
 * bind/unbind 를 제공한다. (회원 대상 판정·template 증강은 SeedChannelTemplatesListener 로 이동.)
 */
class NotificationBindingServiceTest extends PluginTestCase
{
    /**
     * kapi 목록 반환값을 지정한 AlimtalkTemplateService mock 을 만듭니다.
     *
     * @param  array<int, array<string, mixed>>  $templates  list()['templates'] 반환값
     */
    private function fakeTemplateService(array $templates): AlimtalkTemplateService
    {
        $service = Mockery::mock(AlimtalkTemplateService::class);
        $service->shouldReceive('list')->andReturn(['templates' => $templates, 'pagination' => []]);

        return $service;
    }

    private function makeService(array $templates = []): NotificationBindingService
    {
        return new NotificationBindingService(
            new BizppurioNotificationBindingRepository,
            $this->fakeTemplateService($templates),
        );
    }

    public function test_all은_알림톡_연동을_type_키_맵으로_반환한다(): void
    {
        (new BizppurioNotificationBindingRepository)->upsert('order_confirmed', 'alimtalk', [
            'template_code' => 'TW_1234',
            'template_name' => '주문완료',
            'fallback_sms_enabled' => true,
            'is_active' => true,
        ]);

        $map = $this->makeService()->all();

        $this->assertArrayHasKey('order_confirmed', $map);
        $this->assertSame('TW_1234', $map['order_confirmed']['template_code']);
        $this->assertSame('주문완료', $map['order_confirmed']['template_name']);
        $this->assertTrue($map['order_confirmed']['fallback_sms_enabled']);
    }

    public function test_all은_연동이_없으면_빈_맵을_반환한다(): void
    {
        $this->assertSame([], $this->makeService()->all());
    }

    public function test_승인_상태_템플릿만_연결_후보로_노출한다(): void
    {
        $service = $this->makeService([
            ['templateCode' => 'TW_A', 'templateName' => '승인됨', 'serviceStatus' => 'ACT'],
            ['templateCode' => 'TW_B', 'templateName' => '발송전', 'serviceStatus' => 'RDY'],
            ['templateCode' => 'TW_C', 'templateName' => '검수중', 'serviceStatus' => 'REQ'],
            ['templateCode' => 'TW_D', 'templateName' => '반려', 'serviceStatus' => 'REJ'],
        ]);

        $codes = array_column($service->approvedTemplates(), 'template_code');

        $this->assertContains('TW_A', $codes, 'ACT(정상)는 노출되어야 한다.');
        $this->assertContains('TW_B', $codes, 'RDY(발송전)는 노출되어야 한다.');
        $this->assertNotContains('TW_C', $codes, 'REQ(검수중)는 제외되어야 한다.');
        $this->assertNotContains('TW_D', $codes, 'REJ(반려)는 제외되어야 한다.');
    }

    public function test_bind는_연동을_생성하고_unbind는_삭제한다(): void
    {
        $service = $this->makeService();

        $service->bind('welcome', [
            'template_code' => 'TW_1236',
            'template_name' => '가입환영',
            'fallback_sms_enabled' => false,
        ]);

        $this->assertDatabaseHas('bizppurio_notification_bindings', [
            'notification_type' => 'welcome',
            'channel' => 'alimtalk',
            'template_code' => 'TW_1236',
            'is_active' => true,
        ]);

        $service->unbind('welcome');

        $this->assertDatabaseMissing('bizppurio_notification_bindings', [
            'notification_type' => 'welcome',
            'channel' => 'alimtalk',
        ]);
    }

    public function test_bind는_같은_알림에_대해_갱신한다(): void
    {
        $service = $this->makeService();

        $service->bind('welcome', ['template_code' => 'TW_1', 'template_name' => '첫번째']);
        $service->bind('welcome', ['template_code' => 'TW_2', 'template_name' => '두번째']);

        $this->assertDatabaseCount('bizppurio_notification_bindings', 1);
        $this->assertDatabaseHas('bizppurio_notification_bindings', [
            'notification_type' => 'welcome',
            'template_code' => 'TW_2',
        ]);
    }

    public function test_apply_from_template_save는_코드가_있으면_연동을_저장한다(): void
    {
        $this->makeService()->applyFromTemplateSave('welcome', 'TW_9', '가입환영', true);

        $this->assertDatabaseHas('bizppurio_notification_bindings', [
            'notification_type' => 'welcome',
            'channel' => 'alimtalk',
            'template_code' => 'TW_9',
            'fallback_sms_enabled' => true,
        ]);
    }

    public function test_apply_from_template_save는_코드가_비면_연동을_해제한다(): void
    {
        $service = $this->makeService();
        $service->bind('welcome', ['template_code' => 'TW_1', 'template_name' => '기존']);

        // 편집 모달에서 "연결 안 함"으로 저장 → 빈 코드 → 해제
        $service->applyFromTemplateSave('welcome', '', null, false);

        $this->assertDatabaseMissing('bizppurio_notification_bindings', [
            'notification_type' => 'welcome',
            'channel' => 'alimtalk',
        ]);
    }
}
