<?php

namespace Plugins\Sirsoft\MessageBizppurio\Tests\Unit\Services;

use Illuminate\Support\Facades\App;
use Plugins\Sirsoft\MessageBizppurio\Enums\ResultCategory;
use Plugins\Sirsoft\MessageBizppurio\Services\ResultCodeResolver;
use Plugins\Sirsoft\MessageBizppurio\Tests\PluginTestCase;

/**
 * ResultCodeResolver — 결과코드 분류·사유·라벨 검증 (계획서 D11).
 */
class ResultCodeResolverTest extends PluginTestCase
{
    private ResultCodeResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        App::setLocale('ko');
        $this->resolver = new ResultCodeResolver;
    }

    /**
     * 성공 코드(발송 1000 / SMS 4100 / LMS 6600 / 알림톡 7000 / 카카오 200)는 Success.
     */
    public function test_success_codes_categorized_as_success(): void
    {
        foreach (['1000', '4100', '6600', '7000', '200'] as $code) {
            $this->assertSame(ResultCategory::Success, $this->resolver->categorize($code), "코드 {$code}");
            $this->assertTrue($this->resolver->isSuccess($code), "코드 {$code}");
        }
    }

    /**
     * 잔액부족(9070 문자 / 7436 알림톡)은 BalanceLow + isBalanceLow true.
     */
    public function test_balance_low_codes(): void
    {
        foreach (['9070', '7436'] as $code) {
            $this->assertSame(ResultCategory::BalanceLow, $this->resolver->categorize($code), "코드 {$code}");
            $this->assertTrue($this->resolver->isBalanceLow($code), "코드 {$code}");
            $this->assertFalse($this->resolver->isSuccess($code), "코드 {$code}");
        }
    }

    /**
     * 일시오류 코드는 Retry.
     */
    public function test_retryable_codes_categorized_as_retry(): void
    {
        foreach (['5003', '5004', '5005', '9000', '3011', '3013'] as $code) {
            $this->assertSame(ResultCategory::Retry, $this->resolver->categorize($code), "코드 {$code}");
        }
    }

    /**
     * 위 분류에 없는 코드는 영구 실패.
     */
    public function test_unknown_and_failure_codes_are_permanent_failure(): void
    {
        foreach (['4400', '6606', '7204', '2000', '9999'] as $code) {
            $this->assertSame(ResultCategory::PermanentFailure, $this->resolver->categorize($code), "코드 {$code}");
        }
    }

    /**
     * reason 은 lang 정의 코드는 사유를, 미정의 코드는 null 을 반환.
     */
    public function test_reason_resolves_defined_codes_and_null_for_unknown(): void
    {
        $this->assertSame('음영 지역', $this->resolver->reason('4400'));
        $this->assertNull($this->resolver->reason('9999'));
    }

    /**
     * label 은 정의 코드는 "사유 (코드)", 미정의 코드는 코드만 반환.
     */
    public function test_label_formats_reason_with_code(): void
    {
        $this->assertSame('음영 지역 (4400)', $this->resolver->label('4400'));
        $this->assertSame('9999', $this->resolver->label('9999'));
    }
}
