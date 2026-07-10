<?php

namespace Plugins\Sirsoft\MessageBizppurio\Tests\Unit\Services;

use App\Services\PluginSettingsService;
use Mockery;
use Plugins\Sirsoft\MessageBizppurio\Services\MessagePayloadBuilder;
use Plugins\Sirsoft\MessageBizppurio\Tests\PluginTestCase;

/**
 * MessagePayloadBuilder — SMS/LMS/알림톡 payload 조립(부록 C-2 구조) 검증.
 */
class MessagePayloadBuilderTest extends PluginTestCase
{
    private const IDENTIFIER = 'sirsoft-message_bizppurio';

    /**
     * 환경설정 값을 반환하는 PluginSettingsService mock 을 만들어 빌더를 생성합니다.
     *
     * @param  array<string, string>  $settings
     */
    private function makeBuilder(array $settings): MessagePayloadBuilder
    {
        $mock = Mockery::mock(PluginSettingsService::class);
        $mock->shouldReceive('get')
            ->with(self::IDENTIFIER, Mockery::type('string'), Mockery::any())
            ->andReturnUsing(fn ($id, $key, $default) => $settings[$key] ?? $default);

        return new MessagePayloadBuilder($mock);
    }

    public function test_sms_payload_구조(): void
    {
        $builder = $this->makeBuilder([
            'bizppurio_id' => 'acct01',
            'sender_number' => '07012345678',
        ]);

        $payload = $builder->buildSms('01011112222', '테스트 본문', 'ref123');

        $this->assertSame('acct01', $payload['account']);
        $this->assertSame('sms', $payload['type']);
        $this->assertSame('07012345678', $payload['from']);
        $this->assertSame('01011112222', $payload['to']);
        $this->assertSame('ref123', $payload['refkey']);
        $this->assertSame('테스트 본문', $payload['content']['sms']['message']);
    }

    public function test_lms_payload는_subject를_포함한다(): void
    {
        $builder = $this->makeBuilder([
            'bizppurio_id' => 'acct01',
            'sender_number' => '07012345678',
        ]);

        $payload = $builder->buildLms('01011112222', '긴 본문', 'ref456', '제목');

        $this->assertSame('lms', $payload['type']);
        $this->assertSame('제목', $payload['content']['lms']['subject']);
        $this->assertSame('긴 본문', $payload['content']['lms']['message']);
    }

    public function test_lms_subject_null이면_생략된다(): void
    {
        $builder = $this->makeBuilder([
            'bizppurio_id' => 'acct01',
            'sender_number' => '07012345678',
        ]);

        $payload = $builder->buildLms('01011112222', '본문', 'ref789', null);

        $this->assertArrayNotHasKey('subject', $payload['content']['lms']);
    }

    public function test_알림톡_payload는_senderkey와_templatecode를_포함한다(): void
    {
        $builder = $this->makeBuilder([
            'bizppurio_id' => 'acct01',
            'sender_number' => '07012345678',
            'sender_key' => 'senderkey40',
        ]);

        $payload = $builder->buildAlimtalk('01011112222', 'TW_1234', '알림톡 본문', 'ref999', [
            'button' => [['name' => '확인', 'type' => 'WL', 'url_mobile' => 'https://x']],
        ]);

        $this->assertSame('at', $payload['type']);
        $at = $payload['content']['at'];
        $this->assertSame('senderkey40', $at['senderkey']);
        $this->assertSame('TW_1234', $at['templatecode']);
        $this->assertSame('알림톡 본문', $at['message']);
        // extra(버튼) 병합 확인
        $this->assertSame('확인', $at['button'][0]['name']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
