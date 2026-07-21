<?php

namespace Plugins\Sirsoft\MessageBizppurio\Tests\Unit\Services;

use App\Services\PluginSettingsService;
use Illuminate\Support\Facades\Http;
use Mockery;
use Plugins\Sirsoft\MessageBizppurio\Exceptions\BizppurioApiException;
use Plugins\Sirsoft\MessageBizppurio\Services\AlimtalkTemplateService;
use Plugins\Sirsoft\MessageBizppurio\Services\BizppurioKakaoApiClient;
use Plugins\Sirsoft\MessageBizppurio\Tests\PluginTestCase;

/**
 * AlimtalkTemplateService — kapi 조회 위임 + 상태 배지 매핑 검증 (조회 전용).
 */
class AlimtalkTemplateServiceTest extends PluginTestCase
{
    private const IDENTIFIER = 'sirsoft-message_bizppurio';

    /**
     * @param  array<string, string>  $settings
     */
    private function service(array $settings = ['bizppurio_id' => 'biz01', 'api_key' => 'key01', 'sender_key' => 'SK_40']): AlimtalkTemplateService
    {
        $pluginSettings = Mockery::mock(PluginSettingsService::class);
        $pluginSettings->shouldReceive('get')->with(self::IDENTIFIER)->andReturn($settings);

        $kakao = new BizppurioKakaoApiClient($pluginSettings);

        return new AlimtalkTemplateService($kakao, $pluginSettings);
    }

    public function test_목록은_상태_배지를_부가한다(): void
    {
        Http::fake([
            'kapi.ppurio.com/*' => Http::response([
                'code' => '200',
                'totalCount' => 1,
                'totalPage' => 1,
                'currentPage' => 1,
                'data' => ['list' => [
                    ['templateCode' => 'TW_1', 'templateName' => '주문완료', 'serviceStatus' => 'ACT'],
                ]],
            ], 200),
        ]);

        $result = $this->service()->list(['status' => 'ACT']);

        $this->assertCount(1, $result['templates']);
        $tpl = $result['templates'][0];
        $this->assertSame('ACT', $tpl['service_status']);
        $this->assertSame('green', $tpl['status_badge']['variant']);
        // 회귀 방지: 배지 label_key 는 프론트 lang 키 형식(templates.status.*)이어야 한다.
        // 백엔드 messages.php 네임스페이스(::messages.template.status.*)로 주면 프론트 $t() 가
        // 해석하지 못해 라벨 원문이 목록/상세에 그대로 노출된다(PO 브라우저 검수로 발견된 회귀).
        $this->assertSame(
            'sirsoft-message_bizppurio.templates.status.sendable',
            $tpl['status_badge']['label_key'],
        );
        // 조회 전용 — 상태별 가능 액션(available_actions)은 더 이상 부가하지 않는다.
        $this->assertArrayNotHasKey('available_actions', $tpl);
        $this->assertSame(1, $result['pagination']['total']);
    }

    public function test_목록은_status_keyword를_kapi에_전달한다(): void
    {
        Http::fake(['kapi.ppurio.com/*' => Http::response(['code' => '200', 'data' => ['list' => []]], 200)]);

        $this->service()->list(['status' => 'REQ', 'keyword' => '주문', 'page' => 2, 'count' => 10]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v3/kakao/template/list')
                && $request['templateStatus'] === 'REQ'
                && $request['keyword'] === '주문'
                && $request['page'] === 2
                && $request['count'] === 10
                && $request['senderKey'] === 'SK_40';
        });
    }

    public function test_상세는_status_inspection으로_배지를_추론한다(): void
    {
        Http::fake([
            'kapi.ppurio.com/*' => Http::response([
                'code' => '200',
                'data' => [
                    'templateCode' => 'TW_2',
                    'inspectionStatus' => 'APR',
                    'status' => 'A',
                    'block' => false,
                    'dormant' => false,
                ],
            ], 200),
        ]);

        $detail = $this->service()->detail('TW_2');

        // inspection=APR + status=A → ACT(정상)
        $this->assertSame('ACT', $detail['service_status']);
        $this->assertSame('green', $detail['status_badge']['variant']);
    }

    public function test_발신프로필_키_미설정시_예외(): void
    {
        $this->expectException(BizppurioApiException::class);

        $this->service(['bizppurio_id' => 'biz01', 'api_key' => 'key01', 'sender_key' => ''])
            ->list();
    }

    public function test_kapi_실패코드시_예외에_결과코드가_담긴다(): void
    {
        Http::fake([
            'kapi.ppurio.com/*' => Http::response(['code' => '7204', 'message' => '템플릿 불일치'], 200),
        ]);

        try {
            $this->service()->list();
            $this->fail('예외가 발생해야 한다.');
        } catch (BizppurioApiException $e) {
            $this->assertSame('7204', $e->getResultCode());
            $this->assertStringContainsString('템플릿 불일치', $e->getMessage());
        }
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
