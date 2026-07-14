<?php

declare(strict_types=1);

namespace Plugins\Sirsoft\MessageBizppurio\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 이미지형 알림톡 템플릿 이미지 업로드 검증 Request.
 *
 * 카카오 이미지형 템플릿 제한(jpg/png, 최대 500KB, 가로 500px 이상)에 맞춰 파일을 검증한다.
 * 실제 크기·비율 제한은 카카오 업로드 API 가 최종 판정하므로 여기서는 확장자·용량만 사전 차단한다.
 *
 * 인증/권한은 라우트 permission 미들웨어(messaging.manage)가 담당하므로 authorize() 는 true 고정.
 */
class UploadTemplateImageRequest extends FormRequest
{
    /**
     * 권한 검사는 라우트 미들웨어가 담당 — 항상 통과.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * 이미지 업로드 검증 규칙.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // jpg/png, 최대 500KB (카카오 이미지형 제한)
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:500'],
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
            'image' => __('sirsoft-message_bizppurio::messages.template.fields.image'),
        ];
    }
}
