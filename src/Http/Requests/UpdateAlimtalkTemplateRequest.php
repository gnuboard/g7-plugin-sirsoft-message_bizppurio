<?php

declare(strict_types=1);

namespace Plugins\Sirsoft\MessageBizppurio\Http\Requests;

/**
 * 알림톡 템플릿 수정 검증 Request (D12).
 *
 * 등록과 검증 규칙이 동일하다(유형별 조건부 필수·선택항목 동일). 기존 템플릿 코드는 URL
 * path param({templateCode})으로 받으므로 body 에서 검증하지 않는다. 등록 Request 를
 * 상속해 규칙 중복을 피한다.
 *
 * 수정 가능 상태(대기 R + 검수 REG/REJ) 판정은 kapi 가 담당하므로 여기서 검증하지 않는다.
 */
class UpdateAlimtalkTemplateRequest extends StoreAlimtalkTemplateRequest {}
