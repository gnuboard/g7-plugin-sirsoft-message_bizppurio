<?php

declare(strict_types=1);

namespace Plugins\Sirsoft\MessageBizppurio\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 알림톡 템플릿 등록 검증 Request (D12).
 *
 * 화면 입력만 검증한다. senderKey·templateMessageType 은 서비스가 채우므로 여기서
 * 검증하지 않는다(senderKey=환경설정, messageType=부가정보·버튼 유무 자동 계산).
 *
 * 유형별 조건부 필수(templateEmphasizeType, 1차 지원 3종):
 * - NONE(기본형): 추가 필드 없음
 * - IMAGE(이미지형): templateImageName·templateImageUrl 필수
 * - TEXT(강조표기형): templateTitle 필수(변수 가능) + templateSubtitle 필수(변수 불가)
 * ITEM_LIST(아이템리스트형)는 1차 제외 → 목록에서 배제(홈페이지 안내).
 *
 * 인증/권한은 라우트 permission 미들웨어(messaging.manage)가 담당하므로 authorize() 는 true 고정.
 */
class StoreAlimtalkTemplateRequest extends FormRequest
{
    /**
     * 권한 검사는 라우트 미들웨어가 담당 — 항상 통과.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 알림톡 템플릿 등록 검증 규칙.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // 공통 필수
            'templateName' => ['required', 'string', 'max:200'],
            'templateContent' => ['required', 'string', 'max:1300'],
            'categoryCode' => ['required', 'string', 'max:20'],
            'templateEmphasizeType' => ['required', Rule::in(['NONE', 'IMAGE', 'TEXT'])],

            // 템플릿 코드(선택, 자동 생성 가능). 영문·숫자·언더바·하이픈, 최대 30자
            'templateCode' => ['nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],

            // 강조표기형(TEXT) 조건부 필수 — 강조표기 문구(변수 가능) + 보조문구(변수 불가)
            'templateTitle' => ['required_if:templateEmphasizeType,TEXT', 'nullable', 'string', 'max:23'],
            'templateSubtitle' => ['required_if:templateEmphasizeType,TEXT', 'nullable', 'string', 'max:18'],

            // 이미지형(IMAGE) 조건부 필수 — 이미지 파일명 + 링크
            'templateImageName' => ['required_if:templateEmphasizeType,IMAGE', 'nullable', 'string', 'max:255'],
            'templateImageUrl' => ['required_if:templateEmphasizeType,IMAGE', 'nullable', 'string', 'url', 'max:500'],

            // 선택 항목(§6-4): 부가정보 · 미리보기 메시지 · 템플릿 보안
            'templatePreviewMessage' => ['nullable', 'string', 'max:40'],
            'templateExtra' => ['nullable', 'string', 'max:500'],
            'securityFlag' => ['nullable', 'boolean'],

            // 등록 후 즉시 검수요청 여부([등록 후 검수요청] 버튼) — 서비스가 소비, kapi payload 제외
            'requestInspection' => ['nullable', 'boolean'],

            // 버튼(최대 5)
            'buttons' => ['nullable', 'array', 'max:5'],
            'buttons.*.name' => ['required_with:buttons', 'string', 'max:20'],
            'buttons.*.linkType' => ['required_with:buttons', 'string', 'max:4'],
            'buttons.*.linkMo' => ['nullable', 'string', 'max:500'],
            'buttons.*.linkPc' => ['nullable', 'string', 'max:500'],
            'buttons.*.linkAnd' => ['nullable', 'string', 'max:500'],
            'buttons.*.linkIos' => ['nullable', 'string', 'max:500'],

            // 바로연결(최대 10)
            'quickReplies' => ['nullable', 'array', 'max:10'],
            'quickReplies.*.name' => ['required_with:quickReplies', 'string', 'max:20'],
            'quickReplies.*.linkType' => ['required_with:quickReplies', 'string', 'max:4'],

            // 대표 링크(선택) — PC/모바일 웹, 앱 스킴
            'templateRepresentLink' => ['nullable', 'array'],
            'templateRepresentLink.linkPc' => ['nullable', 'string', 'max:500'],
            'templateRepresentLink.linkMo' => ['nullable', 'string', 'max:500'],
            'templateRepresentLink.linkAnd' => ['nullable', 'string', 'max:500'],
            'templateRepresentLink.linkIos' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * 검증 속성 라벨(다국어).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'templateName' => __('sirsoft-message_bizppurio::messages.template.fields.name'),
            'templateContent' => __('sirsoft-message_bizppurio::messages.template.fields.content'),
            'categoryCode' => __('sirsoft-message_bizppurio::messages.template.fields.category'),
            'templateEmphasizeType' => __('sirsoft-message_bizppurio::messages.template.fields.emphasize_type'),
            'templateCode' => __('sirsoft-message_bizppurio::messages.template.fields.code'),
            'templateTitle' => __('sirsoft-message_bizppurio::messages.template.fields.title'),
            'templateSubtitle' => __('sirsoft-message_bizppurio::messages.template.fields.subtitle'),
            'templateImageName' => __('sirsoft-message_bizppurio::messages.template.fields.image_name'),
            'templateImageUrl' => __('sirsoft-message_bizppurio::messages.template.fields.image_url'),
        ];
    }
}
