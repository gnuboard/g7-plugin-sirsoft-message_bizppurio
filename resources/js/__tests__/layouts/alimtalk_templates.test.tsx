/**
 * 비즈뿌리오 메시징 플러그인 알림톡 템플릿 관리 탭 구조 검증 (Phase 5, §6-3·6-4)
 *
 * plugin_settings.json 안에 탭으로 배치된 알림톡 템플릿 관리 화면을 검증한다.
 * - 탭 네비게이션(환경설정 ↔ 알림톡 템플릿) + 배타 전환(activeSettingsTab)
 * - 목록 서브뷰(상태필터·검색·상태배지·상세) / 폼 서브뷰(유형별 필드·변수삽입)
 * - 상세 모달 / readiness 안내 / 데이터소스 / i18n 정합
 *
 * 독립 페이지·메뉴 없음(⚑ 결정 2·3): 설정 페이지 탭으로만 진입.
 */

import { describe, it, expect } from 'vitest';
import layout from '../../../layouts/admin/plugin_settings.json';
import ko from '../../../lang/ko.json';
import en from '../../../lang/en.json';
import { findById, collectI18nKeys, type AnyNode } from './helpers';

const root = layout as unknown as AnyNode;

describe('alimtalk templates — 탭 네비게이션', () => {
    it('탭 네비게이션에 환경설정·알림톡 템플릿 탭 버튼이 있다', () => {
        const nav = findById(root, 'settings_tabs');
        expect(nav).toBeTruthy();
        expect(findById(nav, 'tab_connection')).toBeTruthy();
        expect(findById(nav, 'tab_templates')).toBeTruthy();
    });

    it('알림톡 탭 버튼이 query.tab=templates 로 URL 을 갱신하고 목록을 refetch 한다', () => {
        const raw = JSON.stringify(findById(root, 'tab_templates'));
        expect(raw).toContain('replaceUrl');
        expect(raw).toContain('"tab":"templates"');
        expect(raw).toContain('"templateView":"list"');
        expect(raw).toContain('alimtalk_templates');
    });

    it('탭 패널이 query.tab 으로 배타 전환된다 (새로고침에도 유지)', () => {
        const connection = findById(root, 'connection_tab_panel');
        const templates = findById(root, 'templates_tab_panel');
        expect((connection as { if?: string }).if).toContain("query.tab ?? 'connection') === 'connection'");
        expect((templates as { if?: string }).if).toContain("query.tab ?? 'connection') === 'templates'");
    });

    it('init_actions 가 templates 탭 진입 시 목록을 자동 로드한다 (새로고침 복원)', () => {
        const inits = (root as { init_actions?: AnyNode[] }).init_actions ?? [];
        const refetch = inits.find((a) => a.handler === 'refetchDataSource');
        expect(refetch).toBeTruthy();
        expect((refetch as { if?: string }).if).toContain("query.tab ?? 'connection') === 'templates'");
    });
});

describe('alimtalk templates — 데이터소스', () => {
    it('템플릿 목록·카테고리 데이터소스가 admin API 를 조회한다', () => {
        const sources = (root as { data_sources?: AnyNode[] }).data_sources ?? [];
        const list = sources.find((s) => s.id === 'alimtalk_templates');
        const cats = sources.find((s) => s.id === 'alimtalk_categories');
        expect(list?.endpoint).toBe('/api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates');
        expect(list?.auto_fetch).toBe(false);
        expect(cats?.endpoint).toContain('/alimtalk-templates/categories');
    });
});

describe('alimtalk templates — 목록 서브뷰', () => {
    it('목록 서브뷰가 templateView=list 조건으로 표시된다', () => {
        const view = findById(root, 'templates_list_view');
        expect((view as { if?: string }).if).toContain("templateView ?? 'list') === 'list'");
    });

    it('상태 필터 Select 와 검색 입력이 있다', () => {
        const toolbar = findById(root, 'templates_toolbar');
        const raw = JSON.stringify(toolbar);
        expect(raw).toContain('templateStatus');
        expect(raw).toContain('templateKeyword');
    });

    it('빈 목록에서도 헤더 있는 표(카드)를 항상 표시하고 데이터 유무로 Tbody 를 분기한다', () => {
        const card = findById(root, 'templates_table_card');
        expect(card).toBeTruthy();
        const raw = JSON.stringify(card);
        // 표 헤더는 항상 존재
        expect(raw).toContain('columns.name');
        expect(raw).toContain('columns.status');
        // 데이터 유무 분기 (빈 상태 안내 + 목록 iteration)
        expect(raw).toContain('.length > 0');
        expect(raw).toContain('.length === 0');
        expect(raw).toContain('templates.list.empty');
    });

    it('표가 templates 목록을 iteration 으로 렌더한다', () => {
        const raw = JSON.stringify(findById(root, 'templates_table_card'));
        expect(raw).toContain('alimtalk_templates?.data?.templates');
        expect(raw).toContain('"item_var":"tpl"');
        expect(raw).toContain('tpl.templateName');
        expect(raw).toContain('status_badge');
    });

    it('[새 템플릿] 버튼이 폼 서브뷰로 전환하고 폼을 초기화한다', () => {
        const raw = JSON.stringify(findById(root, 'templates_toolbar'));
        expect(raw).toContain('"templateView":"form"');
        expect(raw).toContain('templateEmphasizeType');
    });
});

describe('alimtalk templates — 폼 서브뷰', () => {
    it('폼 서브뷰가 templateView=form 조건으로 표시된다', () => {
        const view = findById(root, 'templates_form_view');
        expect((view as { if?: string }).if).toContain("templateView === 'form'");
    });

    it('공통 필드(이름·본문)가 수동바인딩(value+onChange)으로 존재한다', () => {
        const raw = JSON.stringify(findById(root, 'templates_form_view'));
        // 이름: value 바인딩 + templateForm.templateName setState (자동바인딩 name 대신)
        expect(raw).toContain('templateForm.templateName');
        expect(raw).toContain('_local.templateForm?.templateName');
        // 본문: templateForm.templateContent 수동바인딩
        expect(raw).toContain('templateForm.templateContent');
    });

    it('강조표기형(TEXT) 필드가 유형 조건으로 표시된다', () => {
        const text = findById(root, 'template_text_fields');
        expect((text as { if?: string }).if).toContain("templateEmphasizeType === 'TEXT'");
        const raw = JSON.stringify(text);
        expect(raw).toContain('templateTitle');
        expect(raw).toContain('templateSubtitle');
    });

    it('이미지형(IMAGE) 필드가 유형 조건으로 표시된다', () => {
        const image = findById(root, 'template_image_fields');
        expect((image as { if?: string }).if).toContain("templateEmphasizeType === 'IMAGE'");
        const raw = JSON.stringify(image);
        expect(raw).toContain('templateImageName');
        expect(raw).toContain('templateImageUrl');
    });

    it('유형 선택 버튼이 1차 지원 3종(NONE/IMAGE/TEXT)만 노출하고 ITEM_LIST 는 없다', () => {
        const raw = JSON.stringify(findById(root, 'templates_form_view'));
        // 버튼 방식: setState 로 templateEmphasizeType 을 직접 설정
        expect(raw).toContain('"templateForm.templateEmphasizeType":"NONE"');
        expect(raw).toContain('"templateForm.templateEmphasizeType":"IMAGE"');
        expect(raw).toContain('"templateForm.templateEmphasizeType":"TEXT"');
        expect(raw).not.toContain('ITEM_LIST');
    });

    it('변수 버튼이 insertVariable 핸들러로 본문 상태(statePath)에 삽입한다', () => {
        const raw = JSON.stringify(findById(root, 'template_variables'));
        expect(raw).toContain('sirsoft-message_bizppurio.insertVariable');
        expect(raw).toContain('"target":"templateContent"');
        expect(raw).toContain('"statePath":"templateForm.templateContent"');
    });

    it('이벤트별 변수 참고표가 토글 없이 항상 표시된다(인라인 배열 iteration)', () => {
        const raw = JSON.stringify(findById(root, 'template_variables'));
        // 참고표 iteration 이 인라인 배열(varref lang 라벨 + 변수 예시)을 렌더
        expect(raw).toContain('templates.varref.welcome');
        expect(raw).toContain('"item_var":"vref"');
        // showVarRef 토글 조건이 없어야 함(항상 표시)
        expect(raw).not.toContain('showVarRef');
    });

    it('선택 항목이 계획서 §6-4 6개(부가정보·미리보기·버튼·바로연결·대표링크·보안) 체크펼침으로 존재하고 헤더는 없다', () => {
        const raw = JSON.stringify(findById(root, 'template_optional'));
        expect(raw).toContain('templateForm.templateExtra');
        expect(raw).toContain('templateForm.templatePreviewMessage');
        expect(raw).toContain('templateForm.buttons');
        expect(raw).toContain('templateForm.quickReplies');
        expect(raw).toContain('templateForm.templateRepresentLink');
        expect(raw).toContain('templateForm.securityFlag');
        // 각 항목 체크박스 토글(_local.opt*)
        expect(raw).toContain('optExtra');
        expect(raw).toContain('optButton');
        expect(raw).toContain('optLink');
        // 헤더(ITEM_LIST 전용, §6-4 선택항목 아님)는 제거됨
        expect(raw).not.toContain('templateForm.templateHeader');
    });

    it('템플릿 코드(자동/직접)·카테고리 2단 드롭다운·이미지 파일첨부가 존재한다', () => {
        const codeField = JSON.stringify(findById(root, 'template_code_field'));
        expect(codeField).toContain('templateCodeMode');
        expect(codeField).toContain('"auto"');
        expect(codeField).toContain('"manual"');

        const catField = JSON.stringify(findById(root, 'template_category_field'));
        expect(catField).toContain('categoryGroup');
        expect(catField).toContain('templateForm.categoryCode');
        expect(catField).toContain('alimtalk_categories');

        const imgField = JSON.stringify(findById(root, 'template_image_fields'));
        expect(imgField).toContain('"type":"file"');
        expect(imgField).toContain('sirsoft-message_bizppurio.uploadTemplateImage');
        expect(imgField).toContain('templateImageUrl');
    });

    it('저장 버튼이 [등록]과 [등록 후 검수요청] 2개로 분리된다', () => {
        const raw = JSON.stringify(findById(root, 'templates_form_view'));
        expect(raw).toContain('"method":"POST"');
        expect(raw).toContain('_local.templateForm');
        expect(raw).toContain('"templateView":"list"');
        // 등록 후 검수요청: requestInspection 플래그를 body 에 실음
        expect(raw).toContain('requestInspection');
        expect(raw).toContain('save_register');
        expect(raw).toContain('save_and_request');
    });
});

describe('alimtalk templates — 상세 모달 & readiness', () => {
    it('상세 모달이 정의되어 있다', () => {
        const modals = (root as { modals?: AnyNode[] }).modals ?? [];
        const detail = modals.find((m) => m.id === 'alimtalk_template_detail_modal');
        expect(detail).toBeTruthy();
    });

    it('readiness 안내가 api_key/sender_key 미설정 시 조건부로 표시된다', () => {
        const readiness = findById(root, 'templates_readiness');
        const cond = (readiness as { if?: string }).if ?? '';
        expect(cond).toContain('api_key');
        expect(cond).toContain('sender_key');
    });
});

describe('alimtalk templates — i18n 정합', () => {
    it('레이아웃 $t: 키가 ko/en 다국어 파일에 모두 존재한다', () => {
        const keys = collectI18nKeys(layout);
        const resolve = (dict: Record<string, unknown>, path: string): unknown =>
            path.split('.').reduce<unknown>((acc, seg) => {
                if (acc && typeof acc === 'object') {
                    return (acc as Record<string, unknown>)[seg];
                }
                return undefined;
            }, dict);

        for (const raw of keys) {
            const path = raw.replace('$t:sirsoft-message_bizppurio.', '');
            expect(resolve(ko, path), `ko 누락: ${path}`).toBeTruthy();
            expect(resolve(en, path), `en 누락: ${path}`).toBeTruthy();
        }
    });
});
