<?php

namespace Plugins\Sirsoft\MessageBizppurio\Tests\Feature\Installation;

use App\Enums\ExtensionOwnerType;
use App\Models\Menu;
use Illuminate\Support\Facades\Schema;
use Plugins\Sirsoft\MessageBizppurio\Models\BizppurioDispatch;
use Plugins\Sirsoft\MessageBizppurio\Plugin;
use Plugins\Sirsoft\MessageBizppurio\Tests\PluginTestCase;

/**
 * 비즈뿌리오 메시징 플러그인 설치 스모크 테스트.
 *
 * plugin.php 가 선언하는 권한/메뉴/알림 정의/설정 스키마 구조와
 * activate/deactivate 의 메뉴 동기화·정리 동작을 검증한다.
 */
class InstallationTest extends PluginTestCase
{
    private Plugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->plugin = new Plugin;
    }

    public function test_식별자가_디렉토리명과_일치한다(): void
    {
        $this->assertSame('sirsoft-message_bizppurio', $this->plugin->getIdentifier());
        $this->assertSame('sirsoft', $this->plugin->getVendor());
    }

    public function test_권한이_view_manage_2개로_선언된다(): void
    {
        $permissions = $this->plugin->getPermissions();

        $this->assertArrayHasKey('categories', $permissions);
        $this->assertCount(1, $permissions['categories']);

        $category = $permissions['categories'][0];
        $this->assertSame('messaging', $category['identifier']);

        $actions = array_column($category['permissions'], 'action');
        $this->assertEqualsCanonicalizing(['view', 'manage'], $actions);

        foreach ($category['permissions'] as $permission) {
            $this->assertContains('admin', $permission['roles']);
            $this->assertSame('admin', $permission['type']);
        }
    }

    public function test_설정_스키마의_크리덴셜은_sensitive다(): void
    {
        $schema = $this->plugin->getSettingsSchema();

        foreach (['password', 'api_key', 'sender_key'] as $credential) {
            $this->assertArrayHasKey($credential, $schema);
            $this->assertTrue(
                $schema[$credential]['sensitive'] ?? false,
                "{$credential} 는 sensitive 로 마킹되어야 한다."
            );
        }

        // 비크리덴셜 필드는 존재하되 sensitive 아님
        $this->assertArrayHasKey('bizppurio_id', $schema);
        $this->assertArrayHasKey('sender_number', $schema);
        $this->assertArrayHasKey('is_test_mode', $schema);
        $this->assertSame('boolean', $schema['is_test_mode']['type']);
        $this->assertTrue($schema['is_test_mode']['default']);
    }

    public function test_defaults_json의_크리덴셜은_expose_false다(): void
    {
        $path = dirname(__DIR__, 3).'/config/settings/defaults.json';
        $this->assertFileExists($path);

        $defaults = json_decode(file_get_contents($path), true);
        $schema = $defaults['frontend_schema'];

        foreach ($schema as $field => $rule) {
            $this->assertFalse(
                $rule['expose'] ?? true,
                "{$field} 는 프론트에 노출(expose:true)되면 안 된다."
            );
        }

        foreach (['password', 'api_key', 'sender_key'] as $credential) {
            $this->assertTrue($schema[$credential]['sensitive'] ?? false);
        }
    }

    public function test_잔액부족_알림이_관리자_대상으로_정의된다(): void
    {
        $definitions = $this->plugin->getNotificationDefinitions();

        $this->assertCount(1, $definitions);
        $definition = $definitions[0];

        $this->assertSame('bizppurio_balance_low', $definition['type']);
        $this->assertSame('sirsoft-message_bizppurio', $definition['hook_prefix']);
        $this->assertEqualsCanonicalizing(['mail', 'database'], $definition['channels']);

        foreach ($definition['templates'] as $template) {
            $recipients = $template['recipients'];
            $this->assertSame('role', $recipients[0]['type']);
            $this->assertSame('admin', $recipients[0]['value']);
        }
    }

    public function test_activate_시_관리자_메뉴가_계층으로_생성된다(): void
    {
        $this->plugin->activate();

        $parent = Menu::where('slug', 'sirsoft-message_bizppurio')
            ->where('extension_type', ExtensionOwnerType::Plugin->value)
            ->first();

        $this->assertNotNull($parent, '최상위 메뉴가 생성되어야 한다.');

        $children = Menu::where('parent_id', $parent->id)->get();
        $this->assertCount(2, $children, '알림톡 템플릿 관리·발송 이력 2개 하위 메뉴가 생성되어야 한다.');

        $childSlugs = $children->pluck('slug')->all();
        $this->assertContains('sirsoft-message_bizppurio-alimtalk-templates', $childSlugs);
        $this->assertContains('sirsoft-message_bizppurio-dispatches', $childSlugs);
    }

    public function test_deactivate_시_메뉴가_제거된다(): void
    {
        $this->plugin->activate();
        $this->assertGreaterThan(0, Menu::where('extension_identifier', 'sirsoft-message_bizppurio')->count());

        $this->plugin->deactivate();

        $this->assertSame(
            0,
            Menu::where('extension_identifier', 'sirsoft-message_bizppurio')->count(),
            '비활성화 시 플러그인 소속 메뉴가 전부 제거되어야 한다.'
        );
    }

    /**
     * Phase 4 발송 이력·연동 테이블이 마이그레이션으로 생성된다.
     */
    public function test_phase4_테이블이_생성된다(): void
    {
        $this->assertTrue(Schema::hasTable('bizppurio_dispatches'));
        $this->assertTrue(Schema::hasTable('bizppurio_notification_bindings'));

        $this->assertTrue(Schema::hasColumns('bizppurio_dispatches', [
            'refkey', 'messagekey', 'channel', 'to_number', 'to_user_id',
            'status', 'result_code', 'reported_at', 'raw_payload',
        ]));
    }

    /**
     * 마이그레이션 up→down→up 왕복 후에도 리포지토리 호출 1회전이 동작한다.
     */
    public function test_마이그레이션_왕복_후_리포지토리가_동작한다(): void
    {
        $migrations = base_path('plugins/_bundled/sirsoft-message_bizppurio/database/migrations');

        // down
        $this->artisan('migrate:rollback', ['--path' => $migrations, '--realpath' => true])->run();
        $this->assertFalse(Schema::hasTable('bizppurio_dispatches'));

        // up
        $this->artisan('migrate', ['--path' => $migrations, '--realpath' => true])->run();
        $this->assertTrue(Schema::hasTable('bizppurio_dispatches'));

        // 왕복 후 Repository 호출 1회전 (create → 조회)
        $dispatch = BizppurioDispatch::create([
            'refkey' => 'roundtrip',
            'channel' => 'sms',
            'to_number' => '01011112222',
            'content' => 'x',
            'status' => 'sent',
            'source' => 'auto',
        ]);
        $this->assertNotNull(BizppurioDispatch::query()->byRefkey('roundtrip')->first());
        $this->assertSame('roundtrip', $dispatch->refkey);
    }
}
