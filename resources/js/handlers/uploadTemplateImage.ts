/**
 * uploadTemplateImage 핸들러
 *
 * 이미지형 알림톡 템플릿 등록 화면에서 파일 선택(change) 시, 선택한 이미지를 플러그인의
 * 이미지 업로드 API(카카오 서버 업로드 위임)로 전송하고, 반환된 카카오 이미지 URL 을
 * `_local.templateForm.templateImageUrl` 에, 파일명을 `templateImageName` 에 설정한다.
 *
 * 업로드는 multipart/form-data 이므로 apiCall 핸들러로는 처리하기 번거로워 전용 핸들러로 둔다.
 * 진행 상태는 `_local.imageUploading`, 오류는 `_local.imageUploadError` 로 노출한다.
 *
 * 레이아웃 JSON 사용 예:
 * {
 *   "type": "change",
 *   "handler": "sirsoft-message_bizppurio.uploadTemplateImage"
 * }
 * (대상 file input 의 change 이벤트에 바인딩; $event.target.files[0] 를 사용)
 */

import type { ActionContext, ActionWithParams } from '../types';

const logger = ((window as any).G7Core?.createLogger?.('MessageBizppurio:UploadImage')) ?? {
    log: (...args: unknown[]) => console.log('[MessageBizppurio:UploadImage]', ...args),
    warn: (...args: unknown[]) => console.warn('[MessageBizppurio:UploadImage]', ...args),
    error: (...args: unknown[]) => console.error('[MessageBizppurio:UploadImage]', ...args),
};

const UPLOAD_URL = '/api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/image';

/**
 * 선택한 이미지 파일을 업로드하고 결과 URL 을 상태에 반영합니다.
 *
 * @param action  액션 객체(미사용 params)
 * @param context  액션 컨텍스트($event 로 파일 접근)
 */
export async function uploadTemplateImageHandler(
    action: ActionWithParams,
    context: ActionContext,
): Promise<void> {
    const G7Core = (window as any).G7Core;
    if (!G7Core?.state?.setLocal) {
        logger.error('G7Core.state.setLocal 을 사용할 수 없습니다.');
        return;
    }

    const event = (context?.event ?? (action as any)?.event) as Event | undefined;
    const input = event?.target as HTMLInputElement | undefined;
    const file = input?.files?.[0];

    if (!file) {
        return;
    }

    G7Core.state.setLocal({ imageUploading: true, imageUploadError: null });

    try {
        const token = localStorage.getItem('auth_token') ?? '';
        const form = new FormData();
        form.append('image', file, file.name);

        const res = await fetch(UPLOAD_URL, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                ...(token ? { Authorization: `Bearer ${token}` } : {}),
            },
            body: form,
        });

        const json = await res.json().catch(() => ({}));

        if (!res.ok || json?.success === false) {
            const message = json?.errors?.kakao_message || json?.message || '이미지 업로드에 실패했습니다.';
            G7Core.state.setLocal({ imageUploading: false, imageUploadError: message });
            logger.warn('이미지 업로드 실패:', message);
            return;
        }

        const url = json?.data?.image_url ?? '';
        const name = json?.data?.image_name ?? file.name;

        G7Core.state.setLocal({
            imageUploading: false,
            imageUploadError: null,
            'templateForm.templateImageUrl': url,
            'templateForm.templateImageName': name,
        });

        logger.log('이미지 업로드 완료:', url);
    } catch (e) {
        G7Core.state.setLocal({ imageUploading: false, imageUploadError: String(e) });
        logger.error('이미지 업로드 예외:', e);
    } finally {
        // 같은 파일 재선택이 가능하도록 input 값 초기화
        if (input) {
            input.value = '';
        }
    }
}
