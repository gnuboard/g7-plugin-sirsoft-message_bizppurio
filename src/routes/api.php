<?php

use App\Http\Middleware\EnforceIdentityPolicy;
use App\Http\Middleware\RefreshTokenExpiration;
use Illuminate\Support\Facades\Route;
use Plugins\Sirsoft\MessageBizppurio\Controllers\BizppurioWebhookController;
use Plugins\Sirsoft\MessageBizppurio\Http\Middleware\BizppurioWebhookIpWhitelist;

/*
|--------------------------------------------------------------------------
| 비즈뿌리오 메시지 발송 플러그인 API 라우트
|--------------------------------------------------------------------------
|
| 코어 PluginRouteServiceProvider 가 자동 적용:
|  - URL prefix: /api/plugins/sirsoft-message_bizppurio
|  - middleware: api
*/

// 비즈뿌리오 webhook(URL PUSH) 리포트 수신 — 외부 시스템이 호출한다.
//
// api 그룹에 appendToGroup 된 토큰/IDV 미들웨어를 라우트 레벨에서 제외(코어 무수정)하고,
// 인증은 IP 화이트리스트로 대체한다(계획서 D13). 항상 200 응답(멱등).
Route::post('/webhook', [BizppurioWebhookController::class, 'handle'])
    ->withoutMiddleware([EnforceIdentityPolicy::class, RefreshTokenExpiration::class])
    ->middleware(BizppurioWebhookIpWhitelist::class)
    ->name('webhook');

Route::prefix('admin')->name('admin.')->middleware(['auth:sanctum', 'admin'])->group(function () {
    // 리포트 수신 주소 조회 (관리자 설정 페이지 표시용)
    //
    // 비즈뿌리오가 발송 결과(리포트)를 URL PUSH 로 전송할 수신 주소를 사이트 도메인
    // 기준 절대 URL 로 내려준다. url() 헬퍼는 리버스 프록시 뒤에서 요청 host 가
    // localhost 로 떨어질 수 있어, 운영자가 관리하는 config('app.url') 을 신뢰 소스로
    // 삼아 절대화한다. 운영자가 접속한 주소와 무관하게 항상 정식 도메인이 표시된다.
    //
    // ※ 실제 리포트 수신 처리(POST /webhook) 는 Phase 4 에서 구현한다.
    Route::get('/report-url', function () {
        $origin = rtrim((string) config('app.url', 'http://localhost'), '/');

        return response()->json([
            'success' => true,
            'data' => [
                'url' => $origin.'/api/plugins/sirsoft-message_bizppurio/webhook',
            ],
        ]);
    })->middleware('permission:admin,core.plugins.read')
        ->name('report.url');
});
