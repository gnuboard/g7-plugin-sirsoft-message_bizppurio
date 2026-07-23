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
    it('환경설정 탭 패널이 dataKey=form + trackChanges 로 자동바인딩한다', () => {
        const container = findById(root, 'connection_tab_panel');
        expect(container).toBeTruthy();
        expect((container as { dataKey?: string }).dataKey).toBe('form');
        expect((container as { trackChanges?: boolean }).trackChanges).toBe(true);
    });
});

describe('plugin_settings.json — 섹션', () => {
    it.each([
        ['preparation_notice', '사용 전 준비 안내(상단)'],
        ['section_api', 'API 연동'],
        ['section_sending', '발송 설정'],
        ['report_section', '리포트 수신 설정'],
    ])('%s 섹션이 존재한다', (id) => {
        expect(findById(root, id)).toBeTruthy();
    });

    it('준비 안내 박스는 총괄 안내(intro)와 콘솔 링크를 구분선 위에 먼저 노출한다', () => {
        const notice = findById(root, 'preparation_notice');
        const raw = JSON.stringify(notice);
        // 총괄 안내 문구 키 + 콘솔 링크가 존재한다
        expect(raw).toContain('sirsoft-message_bizppurio.settings.preparation.intro');
        expect(raw).toContain('sirsoft-message_bizppurio.settings.preparation.console_link');
        // 총괄 안내가 채널별 준비 목록(sms_label)보다 먼저 배치된다 (이미지1 구조)
        const introIdx = raw.indexOf('preparation.intro');
        const smsIdx = raw.indexOf('preparation.sms_label');
        expect(introIdx).toBeGreaterThanOrEqual(0);
        expect(smsIdx).toBeGreaterThanOrEqual(0);
        expect(introIdx).toBeLessThan(smsIdx);
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
        const allowed = [
            'apiCall', 'setState', 'toast', 'navigate', 'sequence', 'switch',
            'refetchDataSource', 'scrollIntoView', 'copyToClipboard',
            'openModal', 'closeModal', 'replaceUrl',
            'sirsoft-message_bizppurio.uploadTemplateImage',
        ];
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

describe('plugin_settings.json — 탭 전환', () => {
    // 탭 버튼은 query.tab 을 바꾸며 화면(if 조건부 패널)을 다시 그려야 하므로
    // replaceUrl(URL만 변경, if 재평가 없음) 이 아니라 navigate 를 써야 한다.
    const tabButtonHandlers = (id: string): string[] => {
        const btn = findById(layout, id);
        const handlers: string[] = [];
        const walk = (node: unknown): void => {
            if (!node || typeof node !== 'object') return;
            const n = node as Record<string, unknown>;
            if (typeof n.handler === 'string') handlers.push(n.handler);
            for (const v of Object.values(n)) {
                if (Array.isArray(v)) v.forEach(walk);
                else if (v && typeof v === 'object') walk(v);
            }
        };
        walk((btn as { actions?: unknown })?.actions);
        return handlers;
    };

    it('환경설정 탭 버튼은 navigate 로 화면을 전환한다 (replaceUrl 금지)', () => {
        const handlers = tabButtonHandlers('tab_connection');
        expect(handlers).toContain('navigate');
        expect(handlers).not.toContain('replaceUrl');
    });

    it('알림톡 템플릿 탭 버튼은 navigate 로 화면을 전환한다 (replaceUrl 금지)', () => {
        const handlers = tabButtonHandlers('tab_templates');
        expect(handlers).toContain('navigate');
        expect(handlers).not.toContain('replaceUrl');
    });
});

describe('plugin_settings.json — 목록 조회 실패 표시', () => {
    it('alimtalk_templates 데이터소스가 실패 사유를 _local.templateListError 에 담는다', () => {
        const sources = (root as { data_sources?: AnyNode[] }).data_sources ?? [];
        const ds = sources.find((s) => s.id === 'alimtalk_templates') as
            | Record<string, unknown>
            | undefined;
        expect(ds).toBeTruthy();
        const onError = JSON.stringify(ds?.onError ?? {});
        // 카카오가 준 사유(kakao_message)를 우선 노출, 없으면 error.message 폴백
        expect(onError).toContain('templateListError');
        expect(onError).toContain('kakao_message');
    });

    it('목록 오류 배너가 오류 존재 + 준비완료(ready) 조건으로 존재한다', () => {
        const banner = findById(layout, 'templates_list_error') as
            | Record<string, unknown>
            | undefined;
        expect(banner).toBeTruthy();
        // 키 미설정(ready=false)일 때는 readiness 안내 배너가 담당 → 빨간 오류 배너는 숨겨
        // 두 배너가 동시에 뜨지 않게 한다. 즉 '키는 넣었는데 다른 이유로 실패'한 경우만 노출.
        expect(banner?.if).toBe('{{_local.templateListError && templates_readiness?.data?.ready}}');
        // 사유 본문(카카오 실제 사유)을 그대로 렌더한다
        expect(JSON.stringify(banner)).toContain('{{_local.templateListError}}');
    });
});
