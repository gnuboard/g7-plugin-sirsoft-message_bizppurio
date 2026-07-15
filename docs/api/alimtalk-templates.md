# Alimtalk Templates API 레퍼런스

> **소유**: plugin `sirsoft-message_bizppurio` · **생성**: `php artisan api:docgen` (실측 기반). @generated 블록은 재생성 시 갱신되며, 사람이 작성한 설명은 보존됩니다.

---

## TL;DR (5초 요약)

```text
1. 이 문서는 실제 API 호출로 실측한 Alimtalk Templates 엔드포인트 레퍼런스입니다
2. 각 엔드포인트: 메서드/URI/권한 + 요청 파라미터 표 + 요청 예시(curl) + 실측 응답 필드 표 + 응답 예시(envelope)
3. 응답 필드의 예시값·응답 예시 JSON 은 실제 호출 응답에서 관측된 값입니다
4. 갱신: 코드 변경 후 php artisan api:docgen 재실행
5. 설명(TODO) 칸은 사람이 채웁니다
```

---


### GET /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.index -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.index`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@index`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.view`

**요청 파라미터**

_요청 파라미터 없음._

**요청 예시**

```http
GET /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: http-422 — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: http-422 — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.view`)이 없는 경우 |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


### POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.store -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.store`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@store`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.manage`

**요청 파라미터**

| 이름 | 위치 | 타입 | 필수 | 허용값 | 용도 |
| --- | --- | --- | --- | --- | --- |
| templateName | body | string | 예 | max 200 | <!-- TODO: 용도 --> |
| templateContent | body | string | 예 | max 1300 | <!-- TODO: 용도 --> |
| categoryCode | body | string | 예 | max 20 | <!-- TODO: 용도 --> |
| templateEmphasizeType | body | string | 예 | — | <!-- TODO: 용도 --> |
| templateCode | body | string | 아니오 | max 30 | <!-- TODO: 용도 --> |
| templateTitle | body | string | 아니오 | max 23 | <!-- TODO: 용도 --> |
| templateSubtitle | body | string | 아니오 | max 18 | <!-- TODO: 용도 --> |
| templateImageName | body | string | 아니오 | max 255 | <!-- TODO: 용도 --> |
| templateImageUrl | body | string | 아니오 | max 500 | <!-- TODO: 용도 --> |
| templatePreviewMessage | body | string | 아니오 | max 40 | <!-- TODO: 용도 --> |
| templateExtra | body | string | 아니오 | max 500 | <!-- TODO: 용도 --> |
| securityFlag | body | boolean | 아니오 | — | <!-- TODO: 용도 --> |
| requestInspection | body | boolean | 아니오 | — | <!-- TODO: 용도 --> |
| buttons | body | array | 아니오 | max 5 | <!-- TODO: 용도 --> |
| quickReplies | body | array | 아니오 | max 10 | <!-- TODO: 용도 --> |
| templateRepresentLink | body | array | 아니오 | — | <!-- TODO: 용도 --> |

**요청 예시**

```http
POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
Content-Type: application/json

{
    "templateName": "예시 이름",
    "templateContent": "예시 내용입니다.",
    "categoryCode": "예시값",
    "templateEmphasizeType": "예시값",
    "templateCode": "예시값",
    "templateTitle": "예시 제목",
    "templateSubtitle": "예시 제목",
    "templateImageName": "예시 이름",
    "templateImageUrl": "https://example.com",
    "templatePreviewMessage": "예시값",
    "templateExtra": "예시값",
    "securityFlag": true,
    "requestInspection": true,
    "buttons": [
        "예시값"
    ],
    "quickReplies": [
        "예시값"
    ],
    "templateRepresentLink": [
        "예시값"
    ]
}
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: http-422 — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: http-422 — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.manage`)이 없는 경우 |
| 422 | Unprocessable Entity | 요청 파라미터가 검증 규칙을 위반한 경우 (`error.errors` 에 필드별 메시지) |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


### GET /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/categories
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.categories -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.categories`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@categories`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.view`

**요청 파라미터**

_요청 파라미터 없음._

**요청 예시**

```http
GET /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/categories HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: http-422 — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: http-422 — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.view`)이 없는 경우 |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


### POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/image
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.image -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.image`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@uploadImage`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.manage`

**요청 파라미터**

| 이름 | 위치 | 타입 | 필수 | 허용값 | 용도 |
| --- | --- | --- | --- | --- | --- |
| image | body | file | 예 | max 500 | <!-- TODO: 용도 --> |

**요청 예시**

```http
POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/image HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
Content-Type: multipart/form-data; boundary=----G7ExampleBoundary

------G7ExampleBoundary
Content-Disposition: form-data; name="image"; filename="example.pdf"
Content-Type: application/octet-stream

(바이너리 파일 내용)
------G7ExampleBoundary--
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: http-422 — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: http-422 — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.manage`)이 없는 경우 |
| 422 | Unprocessable Entity | 요청 파라미터가 검증 규칙을 위반한 경우 (`error.errors` 에 필드별 메시지) |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


### GET /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/profiles
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.profiles -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.profiles`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@profiles`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.view`

**요청 파라미터**

_요청 파라미터 없음._

**요청 예시**

```http
GET /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/profiles HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: http-422 — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: http-422 — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.view`)이 없는 경우 |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


### DELETE /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.destroy -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.destroy`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@destroy`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.manage`

**요청 파라미터**

| 이름 | 위치 | 타입 | 필수 | 허용값 | 용도 |
| --- | --- | --- | --- | --- | --- |
| templateCode | path | string | 예 | — | 대상 template code의 식별자 |

**요청 예시**

```http
DELETE /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode} HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: unresolved-path-param — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: unresolved-path-param — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.manage`)이 없는 경우 |
| 404 | Not Found | path 파라미터에 해당하는 리소스가 없는 경우 |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


### GET /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.show -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.show`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@show`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.view`

**요청 파라미터**

| 이름 | 위치 | 타입 | 필수 | 허용값 | 용도 |
| --- | --- | --- | --- | --- | --- |
| templateCode | path | string | 예 | — | 대상 template code의 식별자 |

**요청 예시**

```http
GET /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode} HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: unresolved-path-param — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: unresolved-path-param — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.view`)이 없는 경우 |
| 404 | Not Found | path 파라미터에 해당하는 리소스가 없는 경우 |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


### PUT /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.update -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.update`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@update`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.manage`

**요청 파라미터**

| 이름 | 위치 | 타입 | 필수 | 허용값 | 용도 |
| --- | --- | --- | --- | --- | --- |
| templateCode | path | string | 예 | — | 대상 template code의 식별자 |
| templateName | body | string | 예 | max 200 | <!-- TODO: 용도 --> |
| templateContent | body | string | 예 | max 1300 | <!-- TODO: 용도 --> |
| categoryCode | body | string | 예 | max 20 | <!-- TODO: 용도 --> |
| templateEmphasizeType | body | string | 예 | — | <!-- TODO: 용도 --> |
| templateTitle | body | string | 아니오 | max 23 | <!-- TODO: 용도 --> |
| templateSubtitle | body | string | 아니오 | max 18 | <!-- TODO: 용도 --> |
| templateImageName | body | string | 아니오 | max 255 | <!-- TODO: 용도 --> |
| templateImageUrl | body | string | 아니오 | max 500 | <!-- TODO: 용도 --> |
| templatePreviewMessage | body | string | 아니오 | max 40 | <!-- TODO: 용도 --> |
| templateExtra | body | string | 아니오 | max 500 | <!-- TODO: 용도 --> |
| securityFlag | body | boolean | 아니오 | — | <!-- TODO: 용도 --> |
| requestInspection | body | boolean | 아니오 | — | <!-- TODO: 용도 --> |
| buttons | body | array | 아니오 | max 5 | <!-- TODO: 용도 --> |
| quickReplies | body | array | 아니오 | max 10 | <!-- TODO: 용도 --> |
| templateRepresentLink | body | array | 아니오 | — | <!-- TODO: 용도 --> |

**요청 예시**

```http
PUT /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode} HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
Content-Type: application/json

{
    "templateName": "예시 이름",
    "templateContent": "예시 내용입니다.",
    "categoryCode": "예시값",
    "templateEmphasizeType": "예시값",
    "templateCode": "예시값",
    "templateTitle": "예시 제목",
    "templateSubtitle": "예시 제목",
    "templateImageName": "예시 이름",
    "templateImageUrl": "https://example.com",
    "templatePreviewMessage": "예시값",
    "templateExtra": "예시값",
    "securityFlag": true,
    "requestInspection": true,
    "buttons": [
        "예시값"
    ],
    "quickReplies": [
        "예시값"
    ],
    "templateRepresentLink": [
        "예시값"
    ]
}
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: unresolved-path-param — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: unresolved-path-param — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.manage`)이 없는 경우 |
| 422 | Unprocessable Entity | 요청 파라미터가 검증 규칙을 위반한 경우 (`error.errors` 에 필드별 메시지) |
| 404 | Not Found | path 파라미터에 해당하는 리소스가 없는 경우 |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


### POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}/cancel-approval
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.cancel-approval -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.cancel-approval`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@cancelApproval`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.manage`

**요청 파라미터**

| 이름 | 위치 | 타입 | 필수 | 허용값 | 용도 |
| --- | --- | --- | --- | --- | --- |
| templateCode | path | string | 예 | — | 대상 template code의 식별자 |

**요청 예시**

```http
POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}/cancel-approval HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: unresolved-path-param — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: unresolved-path-param — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.manage`)이 없는 경우 |
| 404 | Not Found | path 파라미터에 해당하는 리소스가 없는 경우 |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


### POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}/cancel-request
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.cancel-request -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.cancel-request`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@cancelRequest`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.manage`

**요청 파라미터**

| 이름 | 위치 | 타입 | 필수 | 허용값 | 용도 |
| --- | --- | --- | --- | --- | --- |
| templateCode | path | string | 예 | — | 대상 template code의 식별자 |

**요청 예시**

```http
POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}/cancel-request HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: unresolved-path-param — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: unresolved-path-param — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.manage`)이 없는 경우 |
| 404 | Not Found | path 파라미터에 해당하는 리소스가 없는 경우 |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


### POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}/release
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.release -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.release`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@release`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.manage`

**요청 파라미터**

| 이름 | 위치 | 타입 | 필수 | 허용값 | 용도 |
| --- | --- | --- | --- | --- | --- |
| templateCode | path | string | 예 | — | 대상 template code의 식별자 |

**요청 예시**

```http
POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}/release HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: unresolved-path-param — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: unresolved-path-param — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.manage`)이 없는 경우 |
| 404 | Not Found | path 파라미터에 해당하는 리소스가 없는 경우 |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


### POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}/request
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.request -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.request`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@requestInspection`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.manage`

**요청 파라미터**

| 이름 | 위치 | 타입 | 필수 | 허용값 | 용도 |
| --- | --- | --- | --- | --- | --- |
| templateCode | path | string | 예 | — | 대상 template code의 식별자 |

**요청 예시**

```http
POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}/request HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: unresolved-path-param — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: unresolved-path-param — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.manage`)이 없는 경우 |
| 404 | Not Found | path 파라미터에 해당하는 리소스가 없는 경우 |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


### POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}/reuse
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.reuse -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.reuse`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@reuse`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.manage`

**요청 파라미터**

| 이름 | 위치 | 타입 | 필수 | 허용값 | 용도 |
| --- | --- | --- | --- | --- | --- |
| templateCode | path | string | 예 | — | 대상 template code의 식별자 |

**요청 예시**

```http
POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}/reuse HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: unresolved-path-param — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: unresolved-path-param — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.manage`)이 없는 경우 |
| 404 | Not Found | path 파라미터에 해당하는 리소스가 없는 경우 |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


### POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}/stop
<!-- @generated:start:api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.stop -->
- **라우트명**: `api.plugins.sirsoft-message_bizppurio.admin.alimtalk-templates.stop`
- **컨트롤러**: `Plugins\Sirsoft\MessageBizppurio\Controllers\Admin\AlimtalkTemplateController@stop`
- **인증/권한**: `auth:sanctum` + `permission:sirsoft-message_bizppurio.messaging.manage`

**요청 파라미터**

| 이름 | 위치 | 타입 | 필수 | 허용값 | 용도 |
| --- | --- | --- | --- | --- | --- |
| templateCode | path | string | 예 | — | 대상 template code의 식별자 |

**요청 예시**

```http
POST /api/plugins/sirsoft-message_bizppurio/admin/alimtalk-templates/{templateCode}/stop HTTP/1.1
Host: api.example.com
Accept: application/json
Authorization: Bearer {YOUR_TOKEN}
```

**응답 필드** (`data` 내부)

<!-- 실측 제외: unresolved-path-param — 응답 필드는 사람이 작성하세요. -->

**응답 예시**

<!-- 실측 제외: unresolved-path-param — 응답 예시는 사람이 작성하세요. -->

**에러 응답**

| 상태코드 | 의미 | 발생 조건 |
| --- | --- | --- |
| 401 | Unauthenticated | 유효한 Bearer 토큰이 없거나 만료된 경우 |
| 403 | Forbidden | 요구 권한(`sirsoft-message_bizppurio.messaging.manage`)이 없는 경우 |
| 404 | Not Found | path 파라미터에 해당하는 리소스가 없는 경우 |

<!-- @generated:end -->

**설명** <!-- TODO: 이 엔드포인트의 용도·주의사항·예시 시나리오를 작성하세요 -->


