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

describe('plugin_settings.json — 3 섹션', () => {
    it.each([
        ['section_api', 'API 연동'],
        ['section_sending', '발송 설정'],
        ['section_integration', '연동 안내'],
    ])('%s 섹션이 존재한다', (id) => {
        expect(findById(root, id)).toBeTruthy();
    });
});

describe('plugin_settings.json — 입력 필드 6종', () => {
    it.each([
        'environment',
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

    it('environment 는 dev/live 옵션 Select 이다', () => {
        const select = findInputByName(root, 'environment');
        const options = (select?.children ?? []).map(
            (c) => (c.props as { value?: string } | undefined)?.value
        );
        expect(options).toEqual(['dev', 'live']);
    });
});

describe('plugin_settings.json — webhook 안내', () => {
    it('연동 안내 섹션에 고정 webhook 경로가 readonly 로 표시된다', () => {
        const section = findById(root, 'section_integration');
        const inputs = JSON.stringify(section);
        expect(inputs).toContain('/api/plugins/sirsoft-message_bizppurio/webhook');
        expect(inputs).toContain('"readOnly":true');
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
        const allowed = ['apiCall', 'setState', 'toast', 'navigate', 'sequence', 'refetchDataSource', 'scrollIntoView'];
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
