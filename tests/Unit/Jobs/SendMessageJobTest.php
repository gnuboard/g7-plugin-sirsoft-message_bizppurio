<?php

namespace Plugins\Sirsoft\MessageBizppurio\Tests\Unit\Jobs;

use Mockery;
use Plugins\Sirsoft\MessageBizppurio\Exceptions\BizppurioApiException;
use Plugins\Sirsoft\MessageBizppurio\Jobs\SendMessageJob;
use Plugins\Sirsoft\MessageBizppurio\Services\BizppurioApiClient;
use Plugins\Sirsoft\MessageBizppurio\Tests\PluginTestCase;

/**
 * SendMessageJob — 성공/일시오류 재시도/영구실패 즉시 판정 검증.
 */
class SendMessageJobTest extends PluginTestCase
{
    private array $payload = [
        'account' => 'acct', 'type' => 'sms', 'from' => '070', 'to' => '010',
        'refkey' => 'ref1', 'content' => ['sms' => ['message' => 'hi']],
    ];

    /**
     * sendMessage 응답을 반환하는 ApiClient mock 을 만듭니다.
     *
     * @param  array<string, mixed>  $result  sendMessage 반환값
     */
    private function makeClient(array $result): BizppurioApiClient
    {
        $mock = Mockery::mock(BizppurioApiClient::class);
        $mock->shouldReceive('sendMessage')->with($this->payload)->andReturn($result);
        $mock->shouldReceive('isSuccess')
            ->andReturnUsing(fn ($r) => (string) ($r['code'] ?? '') === '1000');

        return $mock;
    }

    public function test_성공시_예외없이_종료(): void
    {
        $job = new SendMessageJob($this->payload, 'ref1');
        $job->handle($this->makeClient(['code' => 1000, 'messagekey' => 'mk']));

        // 예외 미발생 = 정상 완료
        $this->assertTrue(true);
    }

    public function test_일시오류_결과코드는_예외를_던져_재시도한다(): void
    {
        $job = new SendMessageJob($this->payload, 'ref1');

        $this->expectException(BizppurioApiException::class);

        try {
            $job->handle($this->makeClient(['code' => 5003, 'description' => 'temp']));
        } catch (BizppurioApiException $e) {
            $this->assertSame('5003', $e->getResultCode());
            throw $e;
        }
    }

    public function test_영구실패_결과코드는_예외없이_종료된다(): void
    {
        // 3006(계정오류) = 영구실패 → 재시도 안 함(예외 없음)
        $job = new SendMessageJob($this->payload, 'ref1');
        $job->handle($this->makeClient(['code' => 3006, 'description' => 'account error']));

        $this->assertTrue(true);
    }

    public function test_tries와_backoff_기본값(): void
    {
        $job = new SendMessageJob($this->payload, 'ref1');

        $this->assertSame(2, $job->tries);
        $this->assertSame(2, $job->backoff());
    }

    public function test_after_commit_활성화(): void
    {
        $job = new SendMessageJob($this->payload, 'ref1');

        $this->assertTrue($job->afterCommit);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
