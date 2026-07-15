<?php

declare(strict_types=1);

namespace Plugins\Sirsoft\MessageBizppurio\Services;

use App\Models\User;
use App\Notifications\BaseNotification;
use App\Notifications\GenericNotification;
use App\Services\NotificationTemplateService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Plugins\Sirsoft\MessageBizppurio\Enums\DispatchChannel;
use Plugins\Sirsoft\MessageBizppurio\Enums\DispatchSource;
use Plugins\Sirsoft\MessageBizppurio\Enums\DispatchStatus;
use Plugins\Sirsoft\MessageBizppurio\Jobs\SendMessageJob;
use Plugins\Sirsoft\MessageBizppurio\Repositories\Contracts\BizppurioDispatchRepositoryInterface;
use Plugins\Sirsoft\MessageBizppurio\Repositories\Contracts\BizppurioNotificationBindingRepositoryInterface;

/**
 * 코어 알림 시스템의 alimtalk 채널 드라이버 (계획서 §6-2·Phase 6).
 *
 * ChannelManager::extend('alimtalk', …)(ServiceProvider::boot)로 등록되어, 코어가
 * `via()` 에서 'alimtalk' 채널을 선택하면 이 드라이버의 send() 가 호출된다. Phase 3 까지는
 * no-op 스텁이 등록돼 있었고(크래시 방지), 이 드라이버가 그 스텁을 실발송으로 교체한다.
 *
 * SmsChannelDriver 와 흐름은 같으나 핵심 차이는 "발송 대상 템플릿"의 출처다:
 *  - SMS: 코어 알림 템플릿(sms 채널) 본문을 그대로 발송한다.
 *  - 알림톡: 코어 알림 템플릿(alimtalk 채널) 본문을 렌더한 뒤, 관리자가 알림톡 탭에서 연결한
 *    카카오 승인 템플릿(bizppurio_notification_bindings.template_code)으로 발송한다.
 *    연결(binding)이 없으면 발송하지 않는다(알림톡은 임의 본문 발송 불가 — 승인 템플릿 필수).
 *
 * 처리 흐름:
 *  1. 알림 유형(type)으로 활성 binding 조회. 없으면 skip(연결 안 된 알림은 알림톡 미발송).
 *  2. 코어 alimtalk 템플릿 본문 렌더(변수 치환) → 알림톡 변수 형식({x} → #{x})으로 변환.
 *  3. 전화번호 해석(회원=mobile, 비회원=data 의 _recipient_phone — SmsChannelDriver 와 동일 계약).
 *  4. refkey 생성 → payload 조립(대체발송 ON 시 resend/recontent 병합) → 이력 pending →
 *     SendMessageJob 위임.
 *
 * 고아 template_code(연결한 카카오 템플릿이 삭제·차단됨)는 발송 자체는 수행하고, 비즈뿌리오
 * 결과코드(7106/7107/7206 등)로 실패 처리된다(webhook·Job 이 이력에 기록 — Phase 4).
 */
class AlimtalkChannelDriver
{
    /** 알림 data 에서 비회원 전화번호를 싣는 표준 키 (SmsChannelDriver 와 동일 계약) */
    public const RECIPIENT_PHONE_KEY = '_recipient_phone';

    /**
     * @param  NotificationTemplateService  $templateService  alimtalk 채널 본문 템플릿 resolve
     * @param  BizppurioNotificationBindingRepositoryInterface  $bindings  이벤트↔템플릿 연결 조회
     * @param  MessagePayloadBuilder  $payloadBuilder  발송 payload 조립
     * @param  BizppurioDispatchRepositoryInterface  $dispatches  발송 이력 영속화
     */
    public function __construct(
        private readonly NotificationTemplateService $templateService,
        private readonly BizppurioNotificationBindingRepositoryInterface $bindings,
        private readonly MessagePayloadBuilder $payloadBuilder,
        private readonly BizppurioDispatchRepositoryInterface $dispatches,
    ) {}

    /**
     * 알림을 카카오 알림톡으로 발송합니다.
     *
     * Laravel NotificationSender 가 'alimtalk' 채널 드라이버로 이 메서드를 호출한다.
     * GenericNotification 이 아닌 알림은 대상이 아니므로 조용히 무시한다.
     *
     * @param  object  $notifiable  수신자 (User 또는 GuestNotifiable)
     * @param  Notification  $notification  발송 대상 알림
     */
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notification instanceof GenericNotification) {
            return;
        }

        $type = $notification->getType();

        // 1. 이벤트↔알림톡 템플릿 연결 조회 (미연결/비활성이면 알림톡 미발송)
        $binding = $this->bindings->findActive($type, DispatchChannel::Alimtalk->value);
        if ($binding === null) {
            Log::info('비즈뿌리오 알림톡 발송 skip — 연결된 템플릿 없음', ['type' => $type]);

            return;
        }

        // 2. 코어 alimtalk 템플릿 본문 resolve (없으면 발송 안 함 — 기본 body 시드 전제)
        $template = $this->templateService->resolve($type, DispatchChannel::Alimtalk->value);
        if ($template === null || ! $template->is_active) {
            Log::info('비즈뿌리오 알림톡 발송 skip — alimtalk 템플릿 없음', ['type' => $type]);

            return;
        }

        // 3. 전화번호 해석 (회원=mobile, 비회원=data 의 _recipient_phone)
        $to = $this->resolvePhone($notifiable, $notification->getData());
        if ($to === null) {
            Log::info('비즈뿌리오 알림톡 발송 skip — 전화번호 없음', ['type' => $type]);

            return;
        }

        // 4. 본문 렌더 (변수 치환) → 알림톡 변수 형식으로 변환 ({x} → #{x})
        $locale = BaseNotification::resolveNotifiableLocale($notifiable);
        $rendered = $template->replaceVariables($notification->getData(), $locale);
        $message = $this->toAlimtalkVariables((string) ($rendered['body'] ?? ''));
        if (trim($message) === '') {
            Log::info('비즈뿌리오 알림톡 발송 skip — 본문 비어 있음', ['type' => $type]);

            return;
        }

        // 5. refkey 생성 → payload 조립 (대체발송 ON 시 SMS resend 병합)
        $refkey = $this->generateRefkey();
        $payload = $this->payloadBuilder->buildAlimtalk($to, $binding->template_code, $message, $refkey);

        if ($binding->fallback_sms_enabled) {
            $payload = $this->withSmsFallback($payload, (string) ($rendered['body'] ?? ''));
        }

        // 6. 발송 이력 pending 생성 → Job 위임. Job/webhook 이 refkey 로 조회해 상태 갱신.
        $this->dispatches->create([
            'refkey' => $refkey,
            'channel' => DispatchChannel::Alimtalk->value,
            'to_number' => $to,
            'to_name' => $notifiable->name ?? null,
            'to_user_id' => $this->resolveUserId($notifiable),
            'content' => $message,
            'notification_type' => $type,
            'status' => DispatchStatus::Pending->value,
            'source' => DispatchSource::Auto->value,
            'sent_at' => now(),
        ]);

        SendMessageJob::dispatch($payload, $refkey);
    }

    /**
     * 코어 알림 본문의 변수 형식({name})을 알림톡 변수 형식(#{name})으로 변환합니다 (D8).
     *
     * 코어 알림 템플릿과 카카오 알림톡은 변수명 규칙이 동일하되 표기만 다르다. 변수명을 그대로
     * 유지한 채 접두 `#` 만 붙여, 매핑 UI·컬럼 없이 자동 치환한다. 이미 `#{...}` 인 경우는
     * 중복 변환하지 않는다.
     *
     * @param  string  $body  코어 본문 (예: "{name} 님 주문 {order_number}")
     * @return string 알림톡 본문 (예: "#{name} 님 주문 #{order_number}")
     */
    private function toAlimtalkVariables(string $body): string
    {
        // 앞에 # 가 없는 {var} 만 #{var} 로 변환 (이미 #{var} 인 경우 보존).
        return (string) preg_replace('/(?<!#)\{([^{}]+)\}/', '#{$1}', $body);
    }

    /**
     * 알림톡 payload 에 SMS 대체발송(resend/recontent)을 병합합니다 (개별 대체발송, 계획서 §6-2).
     *
     * 알림톡 실패 시(수신 거부·미가입 등) 비즈뿌리오가 SMS 로 대체 발송한다. 대체 SMS 본문은
     * 코어 알림 본문(원문 {var} 형식이 아니라 실제 치환 완료 텍스트)을 재사용한다. 부록 C-2 의
     * `resend:{first:"sms"}` + `recontent:{sms:{message}}` 구조를 따른다.
     *
     * @param  array<string, mixed>  $payload  알림톡 발송 payload
     * @param  string  $renderedBody  치환 완료된 코어 본문(대체 SMS 내용)
     * @return array<string, mixed> resend/recontent 가 병합된 payload
     */
    private function withSmsFallback(array $payload, string $renderedBody): array
    {
        $payload['resend'] = ['first' => 'sms'];
        $payload['recontent'] = ['sms' => ['message' => $renderedBody]];

        return $payload;
    }

    /**
     * 수신자가 회원이면 user id 를, 비회원(GuestNotifiable)이면 null 을 반환합니다.
     *
     * @param  object  $notifiable  수신자
     * @return int|null 회원 ID 또는 null
     */
    private function resolveUserId(object $notifiable): ?int
    {
        if ($notifiable instanceof User) {
            return (int) $notifiable->getKey();
        }

        return null;
    }

    /**
     * 수신자의 전화번호를 해석합니다 (SmsChannelDriver 와 동일 규칙).
     *
     * 회원(Notifiable)은 mobile 속성을, 비회원은 알림 data 의 _recipient_phone 을 사용한다.
     * 숫자 외 문자는 제거하고, 값이 없으면 null 을 반환한다.
     *
     * @param  object  $notifiable  수신자
     * @param  array<string, mixed>  $data  알림 data
     * @return string|null 정규화된 전화번호 또는 null
     */
    private function resolvePhone(object $notifiable, array $data): ?string
    {
        $raw = $notifiable->mobile
            ?? ($data[self::RECIPIENT_PHONE_KEY] ?? null);

        $normalized = preg_replace('/[^0-9]/', '', (string) $raw);

        return ($normalized === null || $normalized === '') ? null : $normalized;
    }

    /**
     * webhook 매칭용 refkey(UTF-8 최대 32byte, unique)를 생성합니다.
     *
     * @return string 32자 refkey
     */
    private function generateRefkey(): string
    {
        return Str::random(32);
    }
}
