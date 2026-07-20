<?php

declare(strict_types=1);

namespace Plugins\Sirsoft\MessageBizppurio\Database\Seeders;

use App\Models\NotificationLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Plugins\Sirsoft\MessageBizppurio\Models\BizppurioDispatch;

/**
 * A-2 데모용 일회성 시더 — 코어 알림 발송 이력 화면에서 비즈뿌리오 결과 컬럼·토글·탭 노출을 눈으로
 * 확인하기 위한 폭넓은 샘플.
 *
 * 두 종류를 넣는다:
 *  (1) 비즈뿌리오 발송(sms/lms/alimtalk) — 코어 로그 + 연결된 bizppurio_dispatch. 결과 컬럼/토글에
 *      상태·`사유 (코드)`·잔액부족·대체발송 배지가 뜬다. 4가지 상태(pending/sent/success/failed)와
 *      엣지 케이스(비회원·미정의 코드·리포트 미수신·대체발송 성공/실패)를 망라한다.
 *  (2) 비-비즈뿌리오 발송(mail/database) — 코어 로그만(dispatch 없음). 메일·사이트내알림 탭에 데이터가
 *      뜨고, 그 탭에선 결과 컬럼(헤더 포함)이 숨겨지는지 확인한다.
 *
 * 자동화 테스트가 아니라 PO 브라우저 검수용이며, 확인 후 지운다:
 *   php artisan db:seed --class="Plugins\\Sirsoft\\MessageBizppurio\\Database\\Seeders\\DispatchResultDemoSeeder"
 *   확인 후 정리: 아래 SOURCE_MARKER 로 삽입분만 삭제(clear() 메서드 참고).
 *
 * 삽입 로그·dispatch 는 SOURCE_MARKER 로 식별되므로 실제 발송 데이터와 섞이지 않는다.
 */
class DispatchResultDemoSeeder extends Seeder
{
    /** 데모 삽입분 식별 마커(source 컬럼). 정리 시 이 값으로만 삭제한다. */
    private const SOURCE_MARKER = 'bizppurio_a2_demo';

    /**
     * 데모 로그·dispatch 를 삽입합니다.
     */
    public function run(): void
    {
        // 재실행 시 중복되지 않도록 기존 데모분을 먼저 정리한다(source 마커 기준, 실제 데이터 무영향).
        $this->clear();

        $bizppurioCases = $this->bizppurioCases();
        $nonBizppurioCases = $this->nonBizppurioCases();

        foreach ($bizppurioCases as $case) {
            $log = $this->createLog($case, hasDispatch: true);
            $this->createDispatch($case, $log->id);
        }

        foreach ($nonBizppurioCases as $case) {
            // 비-비즈뿌리오(mail/database)는 dispatch 를 만들지 않는다(결과 매칭 없음 확인용).
            $this->createLog($case, hasDispatch: false);
        }

        $total = count($bizppurioCases) + count($nonBizppurioCases);
        $this->command?->info('[A-2 데모] 코어 알림 로그 '.$total.'건 삽입 완료 (비즈뿌리오 '.count($bizppurioCases).' + 비-비즈뿌리오 '.count($nonBizppurioCases).').');
        $this->command?->info('[A-2 데모] 전체/SMS/알림톡 탭 = 결과 컬럼 노출, 메일/사이트내알림 탭 = 결과 컬럼(헤더 포함) 숨김을 확인하세요.');
        $this->command?->info('[A-2 데모] 정리: DispatchResultDemoSeeder::clear() — source="'.self::SOURCE_MARKER.'" 로그와 연결 dispatch 만 삭제됩니다.');
    }

    /**
     * 비즈뿌리오 발송(sms/lms/alimtalk) 케이스 — 4가지 상태 + 엣지 케이스 망라.
     *
     * @return array<int, array<string, mixed>>
     */
    private function bizppurioCases(): array
    {
        return [
            // ── SMS ────────────────────────────────────────────────────────────
            ['channel' => 'sms', 'type' => 'welcome', 'name' => '홍길동', 'phone' => '01011110001', 'member' => true, 'status' => 'success', 'code' => '4100', 'fallback' => null],
            ['channel' => 'sms', 'type' => 'order_shipped', 'name' => '김철수', 'phone' => '01011110002', 'member' => true, 'status' => 'failed', 'code' => '4400', 'fallback' => null], // 음영 지역
            ['channel' => 'sms', 'type' => 'reset_password', 'name' => '이수정', 'phone' => '01011110003', 'member' => true, 'status' => 'failed', 'code' => '4410', 'fallback' => null], // 잘못된 번호
            ['channel' => 'sms', 'type' => 'order_confirmed', 'name' => '박영호', 'phone' => '01011110004', 'member' => true, 'status' => 'sent', 'code' => null, 'fallback' => null], // 발송요청 성공, 리포트 대기(코드 없음)
            ['channel' => 'sms', 'type' => 'welcome', 'name' => '정지원', 'phone' => '01011110005', 'member' => true, 'status' => 'pending', 'code' => null, 'fallback' => null], // 발송중(pending)
            ['channel' => 'sms', 'type' => 'order_confirmed', 'name' => null, 'phone' => '01011110006', 'member' => false, 'status' => 'success', 'code' => '4100', 'fallback' => null], // 비회원(수신자 링크 없음)
            ['channel' => 'sms', 'type' => 'order_cancelled', 'name' => '최민아', 'phone' => '01011110007', 'member' => true, 'status' => 'failed', 'code' => '9070', 'fallback' => null], // 잔액 부족(문자)
            ['channel' => 'sms', 'type' => 'order_shipped', 'name' => '강대현', 'phone' => '01011110008', 'member' => true, 'status' => 'failed', 'code' => '9999', 'fallback' => null], // 미정의 코드(사유 없이 코드만)

            // ── LMS ────────────────────────────────────────────────────────────
            ['channel' => 'lms', 'type' => 'order_confirmed', 'name' => '윤서연', 'phone' => '01011110009', 'member' => true, 'status' => 'success', 'code' => '6600', 'fallback' => null], // LMS 성공
            ['channel' => 'lms', 'type' => 'order_delivered', 'name' => '임재훈', 'phone' => '01011110010', 'member' => true, 'status' => 'failed', 'code' => '6603', 'fallback' => null], // LMS 음영 지역

            // ── 알림톡 ──────────────────────────────────────────────────────────
            ['channel' => 'alimtalk', 'type' => 'order_confirmed', 'name' => '한지민', 'phone' => '01011110011', 'member' => true, 'status' => 'success', 'code' => '7000', 'fallback' => null], // 알림톡 성공
            ['channel' => 'alimtalk', 'type' => 'reset_password', 'name' => '오세훈', 'phone' => '01011110012', 'member' => true, 'status' => 'success', 'code' => '7000', 'fallback' => '성공'], // 대체발송 성공
            ['channel' => 'alimtalk', 'type' => 'order_shipped', 'name' => '신유진', 'phone' => '01011110013', 'member' => true, 'status' => 'failed', 'code' => '7436', 'fallback' => '실패'], // 잔액 부족 + 대체발송 실패
            ['channel' => 'alimtalk', 'type' => 'welcome', 'name' => '배준영', 'phone' => '01011110014', 'member' => true, 'status' => 'failed', 'code' => '7206', 'fallback' => null], // 검수 안 된 템플릿
            ['channel' => 'alimtalk', 'type' => 'order_confirmed', 'name' => null, 'phone' => '01011110015', 'member' => false, 'status' => 'success', 'code' => '7000', 'fallback' => null], // 비회원 알림톡
            ['channel' => 'alimtalk', 'type' => 'password_changed', 'name' => '문가영', 'phone' => '01011110016', 'member' => true, 'status' => 'pending', 'code' => null, 'fallback' => null], // 알림톡 발송중
        ];
    }

    /**
     * 비-비즈뿌리오 발송(mail/database) 케이스 — 결과 컬럼 숨김 확인용(dispatch 없음).
     *
     * @return array<int, array<string, mixed>>
     */
    private function nonBizppurioCases(): array
    {
        return [
            ['channel' => 'mail', 'type' => 'welcome', 'name' => '홍보람', 'phone' => 'boram@example.com', 'member' => true, 'status' => 'sent', 'code' => null, 'fallback' => null],
            ['channel' => 'mail', 'type' => 'reset_password', 'name' => '서다인', 'phone' => 'dain@example.com', 'member' => true, 'status' => 'failed', 'code' => null, 'fallback' => null],
            ['channel' => 'mail', 'type' => 'password_changed', 'name' => '남기훈', 'phone' => 'gihun@example.com', 'member' => true, 'status' => 'skipped', 'code' => null, 'fallback' => null],
            ['channel' => 'database', 'type' => 'new_comment', 'name' => '조은채', 'phone' => 'eunchae@example.com', 'member' => true, 'status' => 'sent', 'code' => null, 'fallback' => null],
            ['channel' => 'database', 'type' => 'order_confirmed', 'name' => '권도윤', 'phone' => 'doyoon@example.com', 'member' => true, 'status' => 'sent', 'code' => null, 'fallback' => null],
        ];
    }

    /**
     * 코어 알림 로그 1건을 생성합니다.
     *
     * @param  array<string, mixed>  $case  케이스 정의
     * @param  bool  $hasDispatch  비즈뿌리오 발송 여부(로그 본문 문구용)
     * @return NotificationLog 생성된 로그
     */
    private function createLog(array $case, bool $hasDispatch): NotificationLog
    {
        // 코어 로그 status 는 sent/failed/skipped 만 있으므로, dispatch 의 pending/sent/success 는 sent 로 맵.
        $logStatus = match ($case['status']) {
            'failed' => 'failed',
            'skipped' => 'skipped',
            default => 'sent',
        };

        return NotificationLog::create([
            'channel' => $case['channel'],
            'notification_type' => $case['type'],
            'extension_type' => 'core',
            'extension_identifier' => 'core',
            'recipient_identifier' => $case['phone'],
            'recipient_name' => $case['name'],
            'subject' => $case['channel'] === 'mail' ? '[A-2 데모] '.$case['type'].' 메일' : null,
            'body' => '[A-2 데모] '.$case['type'].' 발송 본문 샘플'.($hasDispatch ? ' (비즈뿌리오)' : ''),
            'status' => $logStatus,
            'source' => self::SOURCE_MARKER,
            'sent_at' => now(),
        ]);
    }

    /**
     * 코어 로그에 연결된 비즈뿌리오 dispatch 1건을 생성합니다.
     *
     * @param  array<string, mixed>  $case  케이스 정의
     * @param  int  $notificationLogId  연결할 코어 로그 id
     */
    private function createDispatch(array $case, int $notificationLogId): void
    {
        BizppurioDispatch::create([
            'refkey' => 'demo_'.Str::random(26),
            'channel' => $case['channel'],
            'to_number' => $case['phone'],
            'to_name' => $case['name'],
            'to_user_id' => null, // 데모는 실제 회원 FK 연결 없음(회원/비회원 구분은 코어 로그 recipient 로 표현)
            'content' => '[A-2 데모] 발송 본문 샘플',
            'notification_type' => $case['type'],
            'notification_log_id' => $notificationLogId,
            'status' => $case['status'],
            'result_code' => $case['code'],
            'result_message' => null,
            'fallback_status' => $case['fallback'],
            'source' => 'auto',
            'sent_at' => now(),
            'reported_at' => in_array($case['status'], ['success', 'failed'], true) ? now() : null,
        ]);
    }

    /**
     * 데모 삽입분만 삭제합니다 (source 마커 기준).
     *
     * 코어 로그를 먼저 찾아 그 id 로 연결된 dispatch 를 지운 뒤 로그를 지운다. 실제 발송 데이터는
     * 이 마커를 갖지 않으므로 영향받지 않는다.
     */
    public function clear(): void
    {
        $logIds = NotificationLog::query()->where('source', self::SOURCE_MARKER)->pluck('id')->all();

        if ($logIds !== []) {
            BizppurioDispatch::query()->whereIn('notification_log_id', $logIds)->delete();
            NotificationLog::query()->whereIn('id', $logIds)->delete();
        }

        $this->command?->info('[A-2 데모] 데모 로그·dispatch '.count($logIds).'건을 정리했습니다.');
    }
}
