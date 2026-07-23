<?php

namespace Plugins\Sirsoft\MessageBizppurio\Tests\Unit\Services;

use Plugins\Sirsoft\MessageBizppurio\Services\AlimtalkPayloadMapper;
use Plugins\Sirsoft\MessageBizppurio\Tests\PluginTestCase;

/**
 * AlimtalkPayloadMapper — 카카오 상세조회 응답 → 발송 API content.at 변환 + 변수 치환 검증 (B안 5-2).
 *
 * 카카오 관리 API 필드(templateContent/buttons.linkMo/…)를 발송 API 필드
 * (message/button.url_mobile/…)로 변환하고, 각 필드의 #{var} 를 알림 data 로 치환한다.
 * 빈/부재 필드는 payload 에 넣지 않는다(방어적).
 */
class AlimtalkPayloadMapperTest extends PluginTestCase
{
    private function mapper(): AlimtalkPayloadMapper
    {
        return new AlimtalkPayloadMapper;
    }

    public function test_본문의_변수를_치환해_message로_반환한다(): void
    {
        $result = $this->mapper()->map(
            ['templateContent' => '#{name}님 주문 #{order_no} 완료'],
            ['name' => '홍길동', 'order_no' => 'A123'],
        );

        $this->assertSame('홍길동님 주문 A123 완료', $result['message']);
    }

    public function test_웹링크_버튼을_발송형식으로_변환하고_url변수를_치환한다(): void
    {
        $result = $this->mapper()->map(
            [
                'templateContent' => '본문',
                'buttons' => [
                    [
                        'name' => '주문조회',
                        'linkType' => 'WL',
                        'linkMo' => 'https://m.shop/orders/#{order_no}',
                        'linkPc' => 'https://shop/orders/#{order_no}',
                    ],
                ],
            ],
            ['order_no' => 'A123'],
        );

        $button = $result['extra']['button'][0];
        $this->assertSame('주문조회', $button['name']);
        $this->assertSame('WL', $button['type']);
        $this->assertSame('https://m.shop/orders/A123', $button['url_mobile']);
        $this->assertSame('https://shop/orders/A123', $button['url_pc']);
        // 카카오 필드명(linkType/linkMo)은 발송 payload 에 남지 않아야 한다.
        $this->assertArrayNotHasKey('linkType', $button);
        $this->assertArrayNotHasKey('linkMo', $button);
    }

    public function test_앱링크_전화_플러그인_버튼_필드를_매핑한다(): void
    {
        $result = $this->mapper()->map(
            [
                'templateContent' => '본문',
                'buttons' => [
                    ['name' => '앱', 'linkType' => 'AL', 'linkAnd' => 'myapp://a', 'linkIos' => 'myapp://i'],
                    ['name' => '전화', 'linkType' => 'TN', 'telNumber' => '1588-0000'],
                    ['name' => '플러그인', 'linkType' => 'P1', 'pluginId' => 'PLUG_1'],
                ],
            ],
            [],
        );

        $buttons = $result['extra']['button'];
        $this->assertSame('myapp://a', $buttons[0]['scheme_android']);
        $this->assertSame('myapp://i', $buttons[0]['scheme_ios']);
        $this->assertSame('1588-0000', $buttons[1]['tel_number']);
        $this->assertSame('PLUG_1', $buttons[2]['plugin_id']);
    }

    public function test_quick_replies를_quickreply로_변환한다(): void
    {
        $result = $this->mapper()->map(
            [
                'templateContent' => '본문',
                'quickReplies' => [
                    ['name' => '바로가기', 'linkType' => 'WL', 'linkMo' => 'https://m.shop'],
                ],
            ],
            [],
        );

        $qr = $result['extra']['quickreply'][0];
        $this->assertSame('바로가기', $qr['name']);
        $this->assertSame('WL', $qr['type']);
        $this->assertSame('https://m.shop', $qr['url_mobile']);
    }

    public function test_title_header를_치환해_매핑한다(): void
    {
        $result = $this->mapper()->map(
            [
                'templateContent' => '본문',
                'templateTitle' => '#{name}님',
                'templateHeader' => '주문 안내',
            ],
            ['name' => '홍길동'],
        );

        $this->assertSame('홍길동님', $result['extra']['title']);
        $this->assertSame('주문 안내', $result['extra']['header']);
    }

    public function test_item과_itemhighlight를_매핑하고_치환한다(): void
    {
        $result = $this->mapper()->map(
            [
                'templateContent' => '본문',
                'templateItem' => [
                    'list' => [
                        ['title' => '상품', 'description' => '#{product}'],
                    ],
                    'summary' => ['title' => '합계', 'description' => '#{total}원'],
                ],
                'templateItemHighlight' => ['title' => '#{name}님', 'description' => '주문완료'],
            ],
            ['product' => '티셔츠', 'total' => '20,000', 'name' => '홍길동'],
        );

        $this->assertSame('티셔츠', $result['extra']['item']['list'][0]['description']);
        $this->assertSame('20,000원', $result['extra']['item']['summary']['description']);
        $this->assertSame('홍길동님', $result['extra']['itemhighlight']['title']);
    }

    public function test_대표링크를_link로_변환하고_치환한다(): void
    {
        $result = $this->mapper()->map(
            [
                'templateContent' => '본문',
                'templateRepresentLink' => [
                    'linkMo' => 'https://m.shop/#{id}',
                    'linkPc' => 'https://shop/#{id}',
                ],
            ],
            ['id' => 'X1'],
        );

        $this->assertSame('https://m.shop/X1', $result['extra']['link']['url_mobile']);
        $this->assertSame('https://shop/X1', $result['extra']['link']['url_pc']);
    }

    public function test_부재_필드는_extra에_넣지_않는다(): void
    {
        // 본문만 있는 단순 템플릿 → extra 는 비어야 한다.
        $result = $this->mapper()->map(['templateContent' => '본문'], []);

        $this->assertSame('본문', $result['message']);
        $this->assertSame([], $result['extra']);
    }

    public function test_빈_버튼배열은_extra에_button을_만들지_않는다(): void
    {
        $result = $this->mapper()->map(
            ['templateContent' => '본문', 'buttons' => []],
            [],
        );

        $this->assertArrayNotHasKey('button', $result['extra']);
    }
}
