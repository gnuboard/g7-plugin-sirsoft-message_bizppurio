/**
 * 비즈뿌리오 메시징 플러그인 환경설정 레이아웃 구조 검증 (§6-1)
 *
 * 3 섹션:
 * 1. section_api (연동 환경 + 비즈뿌리오 아이디 + 비밀번호 + API 키)
 * 2. section_sending (발신번호 + 알림톡 발신프로필 키)
 * 3. section_integration (webhook 수신 주소 안내)
 *
 * 크리덴셜(password/api_key/sender_key)은 type=password 로 마스킹, 저장은
 * 자동바인딩(_local.form) → 코어 /api/admin/plugins/{id}/settings PUT.
 */

import { describe, it, expect } from 'vitest';
import layout from '../../../layouts/admin/plugin_settings.json';
import ko from '../../../lang/ko.json';
import en from '../../../lang/en.json';
import {
    findById,
    findInputByName,
    collectHandlers,
    collectI18nKeys,
    type AnyNode,
} from './helpers';

const root = layout as unknown as AnyNode;

describe('plugin_settings.json — 레이아웃 메타/권한', () => {
    it('layout_name 이 plugin_settings 이다', () => {
        expect((root as { layout_name?: string }).layout_name).toBe('plugin_settings');
    });

    it('_admin_base 를 상속한다', () => {
        expect((root as { extends?: string }).extends).toBe('_admin_base');
    });

    it('core.plugins.update 권한을 요구한다', () => {
        expect((root as { permissions?: string[] }).permissions).toContain('core.plugins.update');
    });

    it('settings 데이터소스가 코어 플러그인 설정 API 를 조회한다', () => {
        const sources = (root as { data_sources?: AnyNode[] }).data_sources ?? [];
        const settings = sources.find((s) => s.id === 'settings');
        expect(settings).toBeTruthy();
        expect(settings?.endpoint).toBe('/api/admin/plugins/{{route.identifier}}/settings');
        expect(settings?.initLocal).toBe('form');
    });
});

describe('plugin_settings.json — 자동바인딩', () => {
    it('컨테이너가 dataKey=form + trackChanges 로 자동바인딩한다', () => {
        const container = findById(root, 'plugin_settings_content');
        expect(container).toBeTruthy();
        expect((container as { dataKey?: string }).dataKey).toBe('form');
        expect((container as { trackChanges?: boolean }).trackChanges).toBe(true);
    });
});

describe('plugin_settings.json — 섹션', () => {
    it.each([
        ['info_panel', '상단 안내 패널'],
        ['section_api', 'API 연동'],
        ['section_sending', '발송 설정'],
        ['report_section', '리포트 수신 설정'],
    ])('%s 섹션이 존재한다', (id) => {
        expect(findById(root, id)).toBeTruthy();
    });

    it('검수 모드 카드에 is_test_mode Toggle 이 있다', () => {
        const card = findById(root, 'test_mode_card');
        expect(card).toBeTruthy();
        const raw = JSON.stringify(card);
        expect(raw).toContain('"Toggle"');
        expect(raw).toContain('is_test_mode');
    });

    it('운영 모드(검수 off) 경고 박스가 조건부로 존재한다', () => {
        const warning = findById(root, 'live_mode_warning');
        expect(warning).toBeTruthy();
        expect((warning as { if?: string }).if).toContain('!_local.form.is_test_mode');
    });
});

describe('plugin_settings.json — 입력 필드 6종', () => {
    it.each([
        'bizppurio_id',
        'password',
        'api_key',
        'sender_number',
        'sender_key',
    ])('%s 입력 필드가 자동바인딩 name 으로 존재한다', (name) => {
        expect(findInputByName(root, name)).toBeTruthy();
    });

    it('크리덴셜(password/api_key/sender_key)은 type=password 로 마스킹된다', () => {
        for (const cred of ['password', 'api_key', 'sender_key']) {
            const input = findInputByName(root, cred);
            expect((input?.props as { type?: string } | undefined)?.type).toBe('password');
        }
    });

    it('bizppurio_id/sender_number 는 일반 text 입력이다', () => {
        for (const field of ['bizppurio_id', 'sender_number']) {
            const input = findInputByName(root, field);
            expect((input?.props as { type?: string } | undefined)?.type).toBe('text');
        }
    });

});

describe('plugin_settings.json — 리포트 수신 설정', () => {
    it('report_url 데이터소스가 조회 엔드포인트를 호출한다', () => {
        const sources = (root as { data_sources?: AnyNode[] }).data_sources ?? [];
        const reportUrl = sources.find((s) => s.id === 'report_url');
        expect(reportUrl).toBeTruthy();
        expect(reportUrl?.endpoint).toBe('/api/plugins/sirsoft-message_bizppurio/admin/report-url');
    });

    it('리포트 섹션에 조회값(fallback 웹훅 경로) readonly 표시 + 복사 버튼이 있다', () => {
        const section = findById(root, 'report_section');
        const raw = JSON.stringify(section);
        expect(raw).toContain('report_url?.data?.url');
        expect(raw).toContain('/api/plugins/sirsoft-message_bizppurio/webhook');
        expect(raw).toContain('"readOnly":true');
        expect(raw).toContain('copyToClipboard');
    });
});

describe('plugin_settings.json — 필드 인라인 에러', () => {
    it.each([
        'bizppurio_id',
        'password',
        'api_key',
        'sender_number',
        'sender_key',
    ])('%s 필드에 인라인 에러 노드가 존재한다', (name) => {
        const errorNode = findById(root, `field_${name}_error`);
        expect(errorNode).toBeTruthy();
        expect((errorNode as { if?: string }).if).toContain(`_local.errors?.${name}`);
    });
});

describe('plugin_settings.json — 저장 버튼', () => {
    it('저장 버튼이 hasChanges 없으면 비활성화된다', () => {
        const save = findById(root, 'save_button');
        expect((save?.props as { disabled?: string } | undefined)?.disabled).toContain('!_local.hasChanges');
    });

    it('저장은 코어 설정 API 로 PUT 한다', () => {
        const text = JSON.stringify(findById(root, 'save_button'));
        expect(text).toContain('/api/admin/plugins/{{route.identifier}}/settings');
        expect(text).toContain('"method":"PUT"');
        expect(text).toContain('{{_local.form}}');
    });

    it('등록된 핸들러만 사용한다 (오탈자 핸들러 없음)', () => {
        const handlers = collectHandlers(layout);
        const allowed = ['apiCall', 'setState', 'toast', 'navigate', 'sequence', 'refetchDataSource', 'scrollIntoView', 'copyToClipboard'];
        for (const h of handlers) {
            expect(allowed).toContain(h);
        }
    });
});

describe('plugin_settings.json — i18n 키 정합', () => {
    it('레이아웃이 참조하는 $t: 키가 ko/en 다국어 파일에 모두 존재한다', () => {
        const keys = collectI18nKeys(layout);
        expect(keys.length).toBeGreaterThan(0);

        const resolve = (dict: Record<string, unknown>, path: string): unknown =>
            path.split('.').reduce<unknown>((acc, seg) => {
                if (acc && typeof acc === 'object') {
                    return (acc as Record<string, unknown>)[seg];
                }
                return undefined;
            }, dict);

        for (const raw of keys) {
            // "$t:sirsoft-message_bizppurio.settings.title" → "settings.title"
            const path = raw.replace('$t:sirsoft-message_bizppurio.', '');
            expect(resolve(ko, path), `ko 누락: ${path}`).toBeTruthy();
            expect(resolve(en, path), `en 누락: ${path}`).toBeTruthy();
        }
    });
});
