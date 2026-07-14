<?php

declare(strict_types=1);

namespace Plugins\Sirsoft\MessageBizppurio\Controllers\Admin;

use App\Helpers\ResponseHelper;
use App\Http\Controllers\Api\Base\AdminBaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Plugins\Sirsoft\MessageBizppurio\Exceptions\BizppurioApiException;
use Plugins\Sirsoft\MessageBizppurio\Http\Requests\StoreAlimtalkTemplateRequest;
use Plugins\Sirsoft\MessageBizppurio\Http\Requests\UpdateAlimtalkTemplateRequest;
use Plugins\Sirsoft\MessageBizppurio\Http\Requests\UploadTemplateImageRequest;
use Plugins\Sirsoft\MessageBizppurio\Services\AlimtalkTemplateService;

/**
 * 알림톡 템플릿 관리 컨트롤러 (Phase 5).
 *
 * 카카오 관리 API(kapi)로 알림톡 템플릿을 실시간 조회·등록·수정·삭제·검수·상태변경한다.
 * 저장(binding)은 하지 않으며(Phase 6), 목록/상세는 매 요청 실시간 조회한다.
 *
 * 권한(라우트 미들웨어):
 * - 조회(list/detail/categories/profiles): sirsoft-message_bizppurio.messaging.view
 * - 변경(store/update/destroy/검수/상태변경): sirsoft-message_bizppurio.messaging.manage
 *
 * kapi 실패는 BizppurioApiException 으로 전달되므로, 각 액션에서 catch 하여 카카오가 준
 * 실패 사유(message)를 그대로 422 로 반환한다(운영자가 반려/차단 사유를 바로 확인).
 */
class AlimtalkTemplateController extends AdminBaseController
{
    /**
     * @param  AlimtalkTemplateService  $service  알림톡 템플릿 서비스
     */
    public function __construct(
        private readonly AlimtalkTemplateService $service,
    ) {
        parent::__construct();
    }

    /**
     * 알림톡 템플릿 목록을 실시간 조회합니다.
     *
     * 쿼리: status(templateStatus)·keyword(2~50자)·page·count
     *
     * @param  Request  $request  HTTP 요청
     */
    public function index(Request $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $result = $this->service->list([
                'status' => $request->query('status'),
                'keyword' => $request->query('keyword'),
                'page' => $request->query('page'),
                'count' => $request->query('count'),
            ]);

            return ResponseHelper::success('messages.success', $result);
        });
    }

    /**
     * 알림톡 템플릿 상세를 실시간 조회합니다.
     *
     * @param  string  $templateCode  템플릿 코드
     */
    public function show(string $templateCode): JsonResponse
    {
        return $this->guard(fn () => ResponseHelper::success('messages.success', [
            'template' => $this->service->detail($templateCode),
        ]));
    }

    /**
     * 템플릿 등록에 사용할 카테고리 전체를 조회합니다.
     */
    public function categories(): JsonResponse
    {
        return $this->guard(fn () => ResponseHelper::success('messages.success', [
            'categories' => $this->service->categories(),
        ]));
    }

    /**
     * 발신프로필(사용중) 정보를 조회합니다.
     */
    public function profiles(): JsonResponse
    {
        return $this->guard(fn () => ResponseHelper::success('messages.success', [
            'profiles' => $this->service->senderProfiles(),
        ]));
    }

    /**
     * 이미지형 템플릿용 이미지를 업로드하고 카카오 등록 URL 을 반환합니다.
     *
     * 업로드된 임시 파일을 카카오 이미지 업로드 API 로 전달하고, 반환된 URL 을 프론트가
     * templateImageUrl 로 사용한다.
     *
     * @param  UploadTemplateImageRequest  $request  이미지 파일 검증 Request
     */
    public function uploadImage(UploadTemplateImageRequest $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $file = $request->file('image');

            $url = $this->service->uploadImage(
                $file->getRealPath(),
                $file->getClientOriginalName(),
            );

            return ResponseHelper::success('messages.success', [
                'image_url' => $url,
                'image_name' => $file->getClientOriginalName(),
            ]);
        });
    }

    /**
     * 알림톡 템플릿을 신규 등록합니다.
     *
     * @param  StoreAlimtalkTemplateRequest  $request  유형별 검증 Request
     */
    public function store(StoreAlimtalkTemplateRequest $request): JsonResponse
    {
        return $this->guard(function () use ($request) {
            $data = $request->validated();
            $requestInspection = (bool) ($data['requestInspection'] ?? false);

            $template = $this->service->create($data);

            // [등록 후 검수요청]: 등록으로 받은 템플릿 코드로 즉시 검수를 요청한다.
            if ($requestInspection) {
                $templateCode = (string) ($template['templateCode'] ?? '');
                if ($templateCode !== '') {
                    $this->service->requestInspection($templateCode);
                }

                return ResponseHelper::success(
                    'sirsoft-message_bizppurio::messages.template.created_requested',
                    ['template' => $template],
                );
            }

            return ResponseHelper::success(
                'sirsoft-message_bizppurio::messages.template.created',
                ['template' => $template],
            );
        });
    }

    /**
     * 알림톡 템플릿을 수정합니다. (대기 + 검수 REG/REJ 상태만)
     *
     * @param  UpdateAlimtalkTemplateRequest  $request  유형별 검증 Request
     * @param  string  $templateCode  기존 템플릿 코드
     */
    public function update(UpdateAlimtalkTemplateRequest $request, string $templateCode): JsonResponse
    {
        return $this->guard(fn () => ResponseHelper::success(
            'sirsoft-message_bizppurio::messages.template.updated',
            ['template' => $this->service->update($templateCode, $request->validated())],
        ));
    }

    /**
     * 알림톡 템플릿을 삭제합니다. (대기 + 검수 REG/REJ 상태만)
     *
     * @param  string  $templateCode  템플릿 코드
     */
    public function destroy(string $templateCode): JsonResponse
    {
        return $this->guard(function () use ($templateCode) {
            $this->service->delete($templateCode);

            return ResponseHelper::success('sirsoft-message_bizppurio::messages.template.deleted');
        });
    }

    /**
     * 검수를 요청합니다. (검수 REG 상태만)
     *
     * @param  Request  $request  HTTP 요청(comment 선택)
     * @param  string  $templateCode  템플릿 코드
     */
    public function requestInspection(Request $request, string $templateCode): JsonResponse
    {
        return $this->guard(function () use ($request, $templateCode) {
            $this->service->requestInspection($templateCode, $request->input('comment'));

            return ResponseHelper::success('sirsoft-message_bizppurio::messages.template.requested');
        });
    }

    /**
     * 검수 요청을 취소합니다. (검수 REQ 상태만)
     *
     * @param  string  $templateCode  템플릿 코드
     */
    public function cancelRequest(string $templateCode): JsonResponse
    {
        return $this->statusAction($templateCode, 'cancelRequest', 'request_canceled');
    }

    /**
     * 승인된 템플릿을 중지합니다. (RDY/ACT 상태)
     *
     * @param  string  $templateCode  템플릿 코드
     */
    public function stop(string $templateCode): JsonResponse
    {
        return $this->statusAction($templateCode, 'stop', 'stopped');
    }

    /**
     * 중지된 템플릿을 정상으로 되돌립니다. (STP 상태)
     *
     * @param  string  $templateCode  템플릿 코드
     */
    public function reuse(string $templateCode): JsonResponse
    {
        return $this->statusAction($templateCode, 'reuse', 'resumed');
    }

    /**
     * 승인을 취소합니다(재검수 가능 상태로 복귀). (RDY/ACT 상태)
     *
     * @param  string  $templateCode  템플릿 코드
     */
    public function cancelApproval(string $templateCode): JsonResponse
    {
        return $this->statusAction($templateCode, 'cancelApproval', 'approval_canceled');
    }

    /**
     * 휴면 템플릿을 해제합니다. (DMT 상태)
     *
     * @param  string  $templateCode  템플릿 코드
     */
    public function release(string $templateCode): JsonResponse
    {
        return $this->statusAction($templateCode, 'release', 'released');
    }

    /**
     * senderKey + templateCode 만 필요한 단순 상태 변경 액션의 공통 처리.
     *
     * @param  string  $templateCode  템플릿 코드
     * @param  string  $method  서비스 메서드명
     * @param  string  $messageKey  성공 메시지 키(messages.template.*)
     */
    private function statusAction(string $templateCode, string $method, string $messageKey): JsonResponse
    {
        return $this->guard(function () use ($templateCode, $method, $messageKey) {
            $this->service->{$method}($templateCode);

            return ResponseHelper::success("sirsoft-message_bizppurio::messages.template.{$messageKey}");
        });
    }

    /**
     * kapi 호출을 감싸 BizppurioApiException 을 422 응답으로 변환합니다.
     *
     * 카카오가 준 실패 사유(message)를 그대로 노출해 운영자가 반려/차단/상태오류 원인을
     * 즉시 파악하게 한다. errors 에 result_code 를 실어 프론트가 코드 분기 가능.
     *
     * @param  callable(): JsonResponse  $callback  실제 처리
     */
    private function guard(callable $callback): JsonResponse
    {
        try {
            return $callback();
        } catch (BizppurioApiException $e) {
            return ResponseHelper::error(
                'sirsoft-message_bizppurio::messages.error.kakao_request_failed',
                422,
                [
                    'kakao_message' => $e->getMessage(),
                    'result_code' => $e->getResultCode(),
                ],
            );
        }
    }
}
