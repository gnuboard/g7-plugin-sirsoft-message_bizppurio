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

/**
 * 코어 알림 시스템의 sms 채널 드라이버.
 *
 * ChannelManager::extend('sms', …)(ServiceProvider::boot)로 등록되어, 코어가
 * `via()` 에서 'sms' 채널을 선택하면 이 드라이버의 send() 가 호출된다. Laravel 채널 계약
 * (`send($notifiable, Notification $notification)`)을 구현한다.
 *
 * 처리 흐름(계획서 Phase 3 ②):
 *  1. sms 채널 알림 템플릿 resolve → 본문 렌더(변수 치환). 템플릿 없으면 skip(Phase 6 에서
 *     3영역 기본 body 시드 예정 — A안 확정).
 *  2. 전화번호 해석: 회원=Notifiable->mobile, 비회원=알림 data 의 _recipient_phone
 *     (게스트 전화번호는 각 도메인 extract_data 리스너가 data 에 주입 — D1).
 *  3. refkey(우리 부여 unique 키) 생성 → SmsTypeResolver 로 SMS/LMS 판별 →
 *     MessagePayloadBuilder 로 payload 조립 → SendMessageJob 위임(발송·재시도는 Job 책임).
 *
 * 발송 이력(bizppurio_dispatches) 영속화는 Phase 4(테이블 신설)에서 이 흐름에 연결한다.
 * Phase 3 은 refkey 생성 + payload 조립 + Job 위임까지 담당한다.
 */
class SmsChannelDriver
{
    /** 알림 data 에서 비회원 전화번호를 싣는 표준 키 (extract_data 리스너와 계약) */
    public const RECIPIENT_PHONE_KEY = '_recipient_phone';

    /**
     * @param  NotificationTemplateService  $templateService  sms 채널 본문 템플릿 resolve
     * @param  SmsTypeResolver  $typeResolver  SMS/LMS byte 판별
     * @param  MessagePayloadBuilder  $payloadBuilder  발송 payload 조립
     * @param  BizppurioDispatchRepositoryInterface  $dispatches  발송 이력 영속화(Phase 4)
     */
    public function __construct(
        private readonly NotificationTemplateService $templateService,
        private readonly SmsTypeResolver $typeResolver,
        private readonly MessagePayloadBuilder $payloadBuilder,
        private readonly BizppurioDispatchRepositoryInterface $dispatches,
    ) {}

    /**
     * 알림을 문자(SMS/LMS)로 발송합니다.
     *
     * Laravel NotificationSender 가 'sms' 채널 드라이버로 이 메서드를 호출한다.
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

        // 1. sms 채널 본문 템플릿 resolve (없으면 발송 안 함 — Phase 6 에서 기본 body 시드)
        $template = $this->templateService->resolve($type, DispatchChannel::Sms->value);
        if ($template === null || ! $template->is_active) {
            Log::info('비즈뿌리오 SMS 발송 skip — sms 템플릿 없음', ['type' => $type]);

            return;
        }

        // 2. 전화번호 해석 (회원=mobile, 비회원=data 의 _recipient_phone)
        $to = $this->resolvePhone($notifiable, $notification->getData());
        if ($to === null) {
            Log::info('비즈뿌리오 SMS 발송 skip — 전화번호 없음', ['type' => $type]);

            return;
        }

        // 3. 본문 렌더 (변수 치환)
        $locale = BaseNotification::resolveNotifiableLocale($notifiable);
        $rendered = $template->replaceVariables($notification->getData(), $locale);
        $message = (string) ($rendered['body'] ?? '');
        if (trim($message) === '') {
            Log::info('비즈뿌리오 SMS 발송 skip — 본문 비어 있음', ['type' => $type]);

            return;
        }

        // 4. refkey 생성 → SMS/LMS 판별 → payload 조립
        $refkey = $this->generateRefkey();
        $channel = $this->typeResolver->resolve($message);

        $payload = $channel === DispatchChannel::Lms
            ? $this->payloadBuilder->buildLms($to, $message, $refkey, (string) ($rendered['subject'] ?? ''))
            : $this->payloadBuilder->buildSms($to, $message, $refkey);

        // 5. 발송 이력 pending 생성(Phase 4) → Job 위임. Job 이 refkey 로 조회해 sent/failed 갱신.
        $this->dispatches->create([
            'refkey' => $refkey,
            'channel' => $channel->value,
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
     * 수신자가 회원이면 user id 를, 비회원(GuestNotifiable)이면 null 을 반환합니다.
     *
     * @param  object  $notifiable  수신자
     * @return int|null 회원 ID 또는 null
     */
    private function resolveUserId(object $notifiable): ?int
    {
        // User 모델만 회원 PK 로 취급. GuestNotifiable 은 DB 회원이 아니므로 null.
        if ($notifiable instanceof User) {
            return (int) $notifiable->getKey();
        }

        return null;
    }

    /**
     * 수신자의 전화번호를 해석합니다.
     *
     * 회원(Notifiable)은 mobile 속성을, 비회원은 알림 data 의 _recipient_phone 을 사용한다.
     * 숫자·하이픈 외 문자는 제거하고, 값이 없으면 null 을 반환한다.
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
     * 32자 hex(=32byte)로 고정해 발송 payload refkey 제약을 만족한다.
     *
     * @return string 32자 refkey
     */
    private function generateRefkey(): string
    {
        return Str::random(32);
    }
}
