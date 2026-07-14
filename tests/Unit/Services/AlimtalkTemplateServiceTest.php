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
 * AlimtalkTemplateService — kapi 위임 + 상태 배지/액션 매핑 + messageType 자동계산 검증.
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

    public function test_목록은_상태_배지와_가능액션을_부가한다(): void
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
        $this->assertEqualsCanonicalizing(['stop', 'cancel_approval'], $tpl['available_actions']);
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

    public function test_등록은_부가정보시_message_type_e_x로_계산한다(): void
    {
        Http::fake(['kapi.ppurio.com/*' => Http::response(['code' => '200', 'data' => ['templateCode' => 'NEW']], 200)]);

        $this->service()->create([
            'templateName' => 'T',
            'templateContent' => '본문',
            'categoryCode' => '001',
            'templateEmphasizeType' => 'NONE',
            'templateExtra' => '부가정보',
        ]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/v3/kakao/template/add')
                && $request['templateMessageType'] === 'EX'
                && $request['templateExtra'] === '부가정보'
                && $request['senderKey'] === 'SK_40';
        });
    }

    public function test_등록은_채널추가버튼시_message_type_a_d로_계산한다(): void
    {
        Http::fake(['kapi.ppurio.com/*' => Http::response(['code' => '200', 'data' => []], 200)]);

        $this->service()->create([
            'templateName' => 'T',
            'templateContent' => '본문',
            'categoryCode' => '001',
            'templateEmphasizeType' => 'NONE',
            'buttons' => [['name' => '채널추가', 'linkType' => 'AC']],
        ]);

        Http::assertSent(fn ($request) => $request['templateMessageType'] === 'AD');
    }

    public function test_등록은_부가정보와_채널추가_둘다면_m_i로_계산한다(): void
    {
        Http::fake(['kapi.ppurio.com/*' => Http::response(['code' => '200', 'data' => []], 200)]);

        $this->service()->create([
            'templateName' => 'T',
            'templateContent' => '본문',
            'categoryCode' => '001',
            'templateEmphasizeType' => 'NONE',
            'templateExtra' => '부가',
            'buttons' => [['name' => '채널추가', 'linkType' => 'AC']],
        ]);

        Http::assertSent(fn ($request) => $request['templateMessageType'] === 'MI');
    }

    public function test_등록은_옵션없으면_b_a로_계산한다(): void
    {
        Http::fake(['kapi.ppurio.com/*' => Http::response(['code' => '200', 'data' => []], 200)]);

        $this->service()->create([
            'templateName' => 'T',
            'templateContent' => '본문',
            'categoryCode' => '001',
            'templateEmphasizeType' => 'NONE',
        ]);

        Http::assertSent(fn ($request) => $request['templateMessageType'] === 'BA');
    }

    public function test_강조표기형은_title_subtitle을_전송한다(): void
    {
        Http::fake(['kapi.ppurio.com/*' => Http::response(['code' => '200', 'data' => []], 200)]);

        $this->service()->create([
            'templateName' => 'T',
            'templateContent' => '본문',
            'categoryCode' => '001',
            'templateEmphasizeType' => 'TEXT',
            'templateTitle' => '강조',
            'templateSubtitle' => '보조',
        ]);

        Http::assertSent(fn ($request) => $request['templateEmphasizeType'] === 'TEXT'
            && $request['templateTitle'] === '강조'
            && $request['templateSubtitle'] === '보조');
    }

    public function test_버튼과_바로연결과_대표링크를_전송한다(): void
    {
        Http::fake(['kapi.ppurio.com/*' => Http::response(['code' => '200', 'data' => []], 200)]);

        $this->service()->create([
            'templateName' => 'T',
            'templateContent' => '본문',
            'categoryCode' => '001',
            'templateEmphasizeType' => 'NONE',
            'buttons' => [['name' => '웹링크', 'linkType' => 'WL', 'linkMo' => 'https://m.example.com']],
            'quickReplies' => [['name' => '바로가기', 'linkType' => 'WL']],
            'templateRepresentLink' => ['linkPc' => 'https://example.com', 'linkMo' => ''],
        ]);

        Http::assertSent(function ($request) {
            return $request['buttons'][0]['linkType'] === 'WL'
                && $request['quickReplies'][0]['name'] === '바로가기'
                // 빈 링크 필드(linkMo='')는 제거되고 linkPc 만 포함
                && $request['templateRepresentLink'] === ['linkPc' => 'https://example.com'];
        });
    }

    public function test_이미지_업로드는_카카오_이미지_ur_l을_반환한다(): void
    {
        Http::fake([
            'kapi.ppurio.com/v3/kakao/image/*' => Http::response([
                'code' => '200',
                'image' => 'https://mud-kage.kakao.com/dn/abc/image.jpg',
            ], 200),
        ]);

        $path = tempnam(sys_get_temp_dir(), 'img');
        file_put_contents($path, 'fake-image-bytes');

        $url = $this->service()->uploadImage($path, 'banner.jpg');

        @unlink($path);

        $this->assertSame('https://mud-kage.kakao.com/dn/abc/image.jpg', $url);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v3/kakao/image/alimtalk/template'));
    }

    public function test_검수요청은_템플릿코드와_comment를_전송한다(): void
    {
        Http::fake(['kapi.ppurio.com/*' => Http::response(['code' => '200'], 200)]);

        $this->service()->requestInspection('TW_1', '검토 부탁드립니다');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v3/kakao/template/request')
            && $request['templateCode'] === 'TW_1'
            && $request['comment'] === '검토 부탁드립니다');
    }

    public function test_중지_상태변경은_stop_엔드포인트를_호출한다(): void
    {
        Http::fake(['kapi.ppurio.com/*' => Http::response(['code' => '200'], 200)]);

        $this->service()->stop('TW_1');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v3/kakao/template/stop')
            && $request['templateCode'] === 'TW_1');
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
            $this->service()->create([
                'templateName' => 'T',
                'templateContent' => '본문',
                'categoryCode' => '001',
                'templateEmphasizeType' => 'NONE',
            ]);
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
