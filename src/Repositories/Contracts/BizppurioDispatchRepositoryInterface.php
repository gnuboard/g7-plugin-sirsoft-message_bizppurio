<?php

declare(strict_types=1);

namespace Plugins\Sirsoft\MessageBizppurio\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Plugins\Sirsoft\MessageBizppurio\Models\BizppurioDispatch;

/**
 * 비즈뿌리오 발송 이력 Repository 계약.
 *
 * 발송 시 pending 이력을 생성하고, webhook 리포트로 refkey 매칭 후 상태를 갱신한다.
 * 발송 이력 화면(계획서 §6-5)의 목록 조회를 담당한다.
 */
interface BizppurioDispatchRepositoryInterface
{
    /**
     * 발송 이력 1건을 생성합니다 (발송 시점, 통상 pending).
     *
     * @param  array<string, mixed>  $data  이력 데이터
     * @return BizppurioDispatch 생성된 이력
     */
    public function create(array $data): BizppurioDispatch;

    /**
     * refkey 로 발송 이력을 조회합니다 (webhook 매칭).
     *
     * @param  string  $refkey  우리 부여 키
     * @return BizppurioDispatch|null 매칭된 이력 또는 null(위조)
     */
    public function findByRefkey(string $refkey): ?BizppurioDispatch;

    /**
     * 발송 이력의 속성을 갱신합니다.
     *
     * @param  BizppurioDispatch  $dispatch  대상 이력
     * @param  array<string, mixed>  $data  갱신 데이터
     * @return BizppurioDispatch 갱신된 이력
     */
    public function update(BizppurioDispatch $dispatch, array $data): BizppurioDispatch;

    /**
     * 필터·검색 조건으로 발송 이력을 페이지네이션 조회합니다 (이력 화면).
     *
     * @param  array<string, mixed>  $filters  channel / status / date_from / date_to / keyword
     * @param  int  $perPage  페이지당 건수
     * @return LengthAwarePaginator<BizppurioDispatch>
     */
    public function paginate(array $filters, int $perPage = 20): LengthAwarePaginator;
}
