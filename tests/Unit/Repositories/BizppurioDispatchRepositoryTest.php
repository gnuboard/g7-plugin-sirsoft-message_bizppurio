<?php

namespace Plugins\Sirsoft\MessageBizppurio\Tests\Unit\Repositories;

use Plugins\Sirsoft\MessageBizppurio\Models\BizppurioDispatch;
use Plugins\Sirsoft\MessageBizppurio\Repositories\BizppurioDispatchRepository;
use Plugins\Sirsoft\MessageBizppurio\Tests\PluginTestCase;

/**
 * BizppurioDispatchRepository — 생성·refkey 조회·갱신·필터 페이지네이션 검증.
 */
class BizppurioDispatchRepositoryTest extends PluginTestCase
{
    private BizppurioDispatchRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new BizppurioDispatchRepository;
    }

    private function makeDispatch(array $overrides = []): BizppurioDispatch
    {
        return $this->repo->create(array_merge([
            'refkey' => 'r'.uniqid(),
            'channel' => 'sms',
            'to_number' => '01011112222',
            'content' => 'x',
            'status' => 'sent',
            'source' => 'auto',
            'sent_at' => now(),
        ], $overrides));
    }

    public function test_create_and_find_by_refkey(): void
    {
        $this->makeDispatch(['refkey' => 'known']);
        $this->assertNotNull($this->repo->findByRefkey('known'));
        $this->assertNull($this->repo->findByRefkey('missing'));
    }

    public function test_update(): void
    {
        $dispatch = $this->makeDispatch(['refkey' => 'upd', 'status' => 'sent']);
        $this->repo->update($dispatch, ['status' => 'success', 'result_code' => '4100']);

        $this->assertSame('success', $dispatch->fresh()->status->value);
        $this->assertSame('4100', $dispatch->fresh()->result_code);
    }

    public function test_paginate_filters_by_channel_and_status(): void
    {
        $this->makeDispatch(['channel' => 'sms', 'status' => 'success']);
        $this->makeDispatch(['channel' => 'alimtalk', 'status' => 'failed']);
        $this->makeDispatch(['channel' => 'sms', 'status' => 'failed']);

        $smsPage = $this->repo->paginate(['channel' => 'sms']);
        $this->assertSame(2, $smsPage->total());

        $failedPage = $this->repo->paginate(['status' => 'failed']);
        $this->assertSame(2, $failedPage->total());
    }

    public function test_paginate_keyword_matches_number_or_refkey(): void
    {
        $this->makeDispatch(['refkey' => 'refABC', 'to_number' => '01099998888']);
        $this->makeDispatch(['refkey' => 'other', 'to_number' => '01000000000']);

        $byRefkey = $this->repo->paginate(['keyword' => 'refABC']);
        $this->assertSame(1, $byRefkey->total());

        $byNumber = $this->repo->paginate(['keyword' => '9999']);
        $this->assertSame(1, $byNumber->total());
    }
}
