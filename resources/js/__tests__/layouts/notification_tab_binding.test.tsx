/**
 * 알림 설정 알림톡 연동 overlay 구조 검증 (Phase 6 재설계, §6-2)
 *
 * notification_tab_core.json 은 코어 admin_settings 편집 모달(alimtalk 채널)에 전용 칸을 얹고,
 * 알림톡·SMS 탭 상단에 상태 배너를 얹는다. 코어 편집 모달·저장 버튼은 건드리지 않으며(무오염),
 * 전용 칸 값을 바꾸면 즉시 우리 API 로 저장한다(별도 저장 버튼 없음).
 *
 * 검증:
 * - 데이터소스(bizppurioBindings·bizppurioApprovedTemplates) 주입
 * - 전용 칸: 안내 카드 · 연결 템플릿 드롭다운(즉시 저장) · SMS 대체 토글(연결 없으면 비활성)
 * - 상태 배너: readiness 미충족(설정하기) · 테스트 모드
 * - 코어 편집 모달 저장 body 에 bizppurio 필드가 없다(무오염)
 * - i18n 키 정합(ko·en 동일)
 */

import { describe, it, expect } from 'vitest';
import overlay from '../../../extensions/notification_tab_core.json';
import ko from '../../../lang/ko.json';
import en from '../../../lang/en.json';
import { findById, type AnyNode } from './helpers';

/** overlay 텍스트에서 $t:key 및 $t('key') 형태의 플러그인 i18n 키를 모두 수집한다. */
const collectPluginKeys = (json: unknown): string[] => {
    const text = JSON.stringify(json);
    const prefixed = text.match(/\$t:sirsoft-message_bizppurio\.[a-zA-Z0-9_.]+/g) ?? [];
    const called = text.match(/\$t\('sirsoft-message_bizppurio\.[a-zA-Z0-9_.]+'\)/g) ?? [];
    const keys = [
        ...prefixed.map((m) => m.replace('$t:', '')),
        ...called.map((m) => m.replace(/^\$t\('/, '').replace(/'\)$/, '')),
    ];
    return Array.from(new Set(keys));
};

type Overlay = {
    target_layout: string;
    data_sources?: Array<{ id: string; endpoint: string }>;
    injections: Array<{ target_id: string; position: string; components: AnyNode[] }>;
};

const ext = overlay as unknown as Overlay;

/** injection components 를 하나의 검색 가능한 루트로 감싼다. */
const injectionRoot = (targetId: string): AnyNode => {
    const inj = ext.injections.find((i) => i.target_id === targetId);
    return { children: inj?.components ?? [] } as AnyNode;
};

describe('notification binding overlay — 대상·데이터소스', () => {
    it('코어 admin_settings 레이아웃을 대상으로 한다', () => {
        expect(ext.target_layout).toBe('admin_settings');
    });

    it('연동 맵·승인 템플릿 데이터소스를 주입한다', () => {
        const ids = (ext.data_sources ?? []).map((d) => d.id);
        expect(ids).toContain('bizppurioBindings');
        expect(ids).toContain('bizppurioApprovedTemplates');
    });

    it('전용 칸은 코어 편집 모달의 template_variables_info 뒤에 append 된다', () => {
        const inj = ext.injections.find((i) => i.target_id === 'template_variables_info');
        expect(inj).toBeTruthy();
        expect(inj?.position).toBe('append');
    });
});

describe('notification binding overlay — 전용 칸', () => {
    const root = injectionRoot('template_variables_info');

    it('알림톡 전용 섹션은 channel === alimtalk 일 때만 노출된다', () => {
        const section = findById(root, 'bizppurio_alimtalk_section');
        expect(section).toBeTruthy();
        expect((section as { if?: string }).if).toContain("=== 'alimtalk'");
    });

    it('연결 템플릿 드롭다운을 바꾸면 즉시 우리 API 로 저장한다(별도 저장 버튼 없음)', () => {
        const raw = JSON.stringify(findById(root, 'bizppurio_binding_template_select'));
        expect(raw).toContain('apiCall');
        expect(raw).toContain('/api/plugins/sirsoft-message_bizppurio/admin/notification-bindings');
        expect(raw).toContain('"method":"POST"');
        expect(raw).toContain('binding.saved');
    });

    it('SMS 대체 토글은 연결 템플릿이 없으면 비활성이고, 변경 시 즉시 저장한다', () => {
        const toggleWrap = findById(root, 'bizppurio_binding_fallback_toggle');
        const raw = JSON.stringify(toggleWrap);
        expect(raw).toContain('"disabled"');
        expect(raw).toContain("=== ''");
        expect(raw).toContain('apiCall');
        expect(raw).toContain('"method":"POST"');
    });

    it('저장 실패 시 에러 토스트를 띄운다', () => {
        const raw = JSON.stringify(root);
        expect(raw).toContain('binding.save_error');
    });
});

describe('notification binding overlay — 상태 배너', () => {
    const root = injectionRoot('notif_channel_content');

    it('배너는 sms·alimtalk 탭에서 문제가 있을 때만 노출된다', () => {
        const banner = findById(root, 'bizppurio_status_banner');
        expect(banner).toBeTruthy();
        const cond = (banner as { if?: string }).if ?? '';
        expect(cond).toContain("'sms'");
        expect(cond).toContain("'alimtalk'");
        expect(cond).toContain('readiness?.ready === false');
        expect(cond).toContain('is_test_mode === true');
    });

    it('readiness 미충족 배너에 설정하기 이동 버튼이 있다', () => {
        const raw = JSON.stringify(findById(root, 'bizppurio_banner_not_ready'));
        expect(raw).toContain('banner.not_ready');
        expect(raw).toContain('banner.setup_action');
        expect(raw).toContain('/admin/plugins/sirsoft-message_bizppurio/settings');
    });

    it('테스트 모드 배너는 readiness 충족 + is_test_mode 일 때만 노출된다', () => {
        const testBanner = findById(root, 'bizppurio_banner_test_mode');
        const cond = (testBanner as { if?: string }).if ?? '';
        expect(cond).toContain('readiness?.ready !== false');
        expect(cond).toContain('is_test_mode === true');
        expect(JSON.stringify(testBanner)).toContain('banner.test_mode');
    });
});

describe('notification binding overlay — i18n 정합', () => {
    it('overlay 가 참조하는 모든 키가 ko·en 에 존재한다', () => {
        const keys = collectPluginKeys(overlay);
        expect(keys.length).toBeGreaterThan(0);

        // 플러그인 resources/lang/{ko,en}.json 파일 루트가 곧 sirsoft-message_bizppurio 네임스페이스.
        // 키 `sirsoft-message_bizppurio.binding.saved` → 첫 세그먼트(네임스페이스) 제거 후 파일 루트에서 해석.
        const resolve = (root: unknown, path: string): unknown =>
            path.split('.').slice(1).reduce<unknown>((acc, seg) => (acc as Record<string, unknown>)?.[seg], root);

        for (const key of keys) {
            expect(resolve(ko, key), `ko 누락: ${key}`).toBeTruthy();
            expect(resolve(en, key), `en 누락: ${key}`).toBeTruthy();
        }
    });
});