<?php

declare(strict_types=1);

namespace Plugins\Sirsoft\MessageBizppurio\Providers;

use App\Extension\BasePluginServiceProvider;

/**
 * 비즈뿌리오 메시징 플러그인 서비스 프로바이더.
 *
 * BasePluginServiceProvider 가 Repository / Storage / Cache 자동 바인딩과
 * 다국어 로드를 처리한다. 아래 배열에 등록만 하면 contextual binding 이 적용된다.
 */
class MessageBizppurioServiceProvider extends BasePluginServiceProvider
{
    /** 플러그인 식별자 (manifest 와 일치) */
    protected string $pluginIdentifier = 'sirsoft-message_bizppurio';

    /**
     * Repository 인터페이스 ↔ 구현체 매핑.
     *
     * Phase 4 에서 발송 이력·이벤트 연동 Repository 를 등록한다.
     *
     * @var array<class-string, class-string>
     */
    protected array $repositories = [
        // Phase 4: BizppurioDispatchRepositoryInterface / BizppurioNotificationBindingRepositoryInterface
    ];

    /**
     * CacheInterface 가 필요한 서비스 (contextual binding).
     *
     * Phase 2 에서 발송 토큰 캐시 서비스(BizppurioTokenService)를 등록한다.
     *
     * @var array<int, class-string>
     */
    protected array $cacheServices = [
        // Phase 2: BizppurioTokenService::class
    ];

    /**
     * 플러그인 루트 lang 디렉토리를 로드합니다.
     *
     * 백엔드 다국어는 lang/{ko,en}/*.php 에 두고 `$this->translationNamespace()`
     * (= 플러그인 식별자) 네임스페이스로 로드한다.
     *
     * @return void
     */
    protected function loadExtensionTranslations(): void
    {
        $langPath = dirname($this->getProviderPath(), 2).'/lang';

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->translationNamespace());
        }
    }
}
