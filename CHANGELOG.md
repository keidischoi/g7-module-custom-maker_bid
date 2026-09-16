# Changelog

이 프로젝트의 모든 주요 변경사항을 기록합니다.
형식은 [Keep a Changelog](https://keepachangelog.com/ko/1.1.0/)를 따르며,
[Semantic Versioning](https://semver.org/lang/ko/)을 준수합니다.

## [0.8.2] - 2026-09-16

### Changed

- 입찰자 등록 **「입찰자 등록」** 제출 버튼을 페이지 **오른쪽 위**(제목 줄, 탭과 섞이지 않음)로 둡니다. 양식 하단 버튼은 제거합니다. `POST /api/modules/custom-maker_bids/companies`, `auth_required`. 처음 신청·거절 회원에게만 보이며, 보류/승인은 상태만 표시합니다.
- 버전 **0.8.2**. nav/form.js·form.css 캐시 `?v=0.8.2`.

## [0.8.1] - 2026-09-16

### Fixed

- 입찰자 등록 양식 **하단**에 **「입찰자 등록」** 제출 버튼을 다시 둡니다 (`POST /api/modules/custom-maker_bids/companies`). 처음 신청하는 회원(상태 없음·거절)에게는 카드와 버튼이 항상 보입니다. 보류/승인된 회원은 상태만 보고 제출 버튼은 숨깁니다. 헤더 오른쪽 위 버튼도 유지합니다.

### Changed

- 버전 **0.8.1**.

## [0.8.0] - 2026-09-16

### Changed

- **모듈 identifier 변경 (breaking)**: `custom-maker_bid` → `custom-maker_bids`. 권한·API prefix·에셋 URL·저장 경로 prefix·프론트 경로(`/maker-bids`, `/admin/maker-bids`)를 맞췄습니다. **재설치 필요.** 예전 identifier와의 호환은 없습니다.
- 관리자 UI 정리: 입력 너비를 용도에 맞게, 목록 액션 오른쪽 정렬, 유형 관리 체크박스 라벨 대비, 회사 목록 카드/2열 레이아웃.
- 입찰자 등록 화면의 **「입찰자 등록」** 버튼을 페이지 **오른쪽 위**로 옮겼습니다. 이미 보류/승인된 회원에게는 버튼을 숨기고 상태만 보여 줍니다.
- 버전 **0.8.0**. nav/form.js·form.css 캐시 `?v=0.8.0`.
- DB 테이블 이름(`maker_*`)은 그대로입니다. PHP namespace `Modules\Custom\MakerBid` 도 기존 autoload를 유지합니다.

## [0.7.2] - 2026-09-16

### Changed

- **입찰 허용 권한** 옵션을 7개로 교체: 모두 / 관리자 / 지정업체 / 모든 등록된 업체 / 등록된 개인회원 / 모든 등록된 업체 & 등록된 개인회원 / 일반회원. 예전 짧은 목록(모두/관리자/지정업체/업체/개인)은 Select에서 제거했습니다. 저장값은 `all` · `admin` · `designated` · `approved_company` · `approved_individual` · `approved_bidders` · `member`. 예전 `members`/`company`/`individual` 은 읽어올 때 매핑합니다.
- **모두**: 이 제품은 게스트 입찰이 없어 로그인 회원 전체입니다. **일반회원**: 업체·개인 입찰자 등록(승인)이 없는 로그인 회원입니다.
- 버전 **0.7.2**. nav/form.js·form.css 캐시 `?v=0.7.2`.

## [0.7.1] - 2026-09-16

### Added

- 관리자 설정 **입찰 허용 권한** (0.7.2에서 7개로 정리). `general.bid_allow`. 의뢰 공개 설정과 AND. 관리자 모드일 때만 공개 대상을 건너뜁니다. 관리자 입찰 API는 이 제한을 타지 않습니다.
- 업체 `is_designated`(지정업체) additive 컬럼. 관리자 회사 목록에서 토글. 입찰자 등록 화면은 읽기 전용(관리자만 지정). **지정업체** 모드에서는 승인+지정 플래그인 업체만 입찰할 수 있습니다. 비허용 시 한국어 403.

### Changed

- 버전 **0.7.1**. nav/form.js·form.css 캐시 `?v=0.7.1`.
- 0.6.1 소유자 목록/상세(`optional.sanctum`, `auth_mode: "optional"`)는 그대로입니다.

### Notes

- `php artisan module:update custom-maker_bids` 후 **캐시 삭제와 하드 리프레시**. `is_designated` 마이그레이션이 적용됩니다.

## [0.7.0] - 2026-09-16

### Added

- 관리자 의뢰 상세에서 **필드 수정·저장**. 제목·유형·상태(보류/의뢰/견적요청/open/낙찰/완료/취소)·공개 설정·예산·마감·급행/선점/수정·크기·확장자·설명·연락처·담당자·소유권한 요청. 기존 `PATCH /admin/jobs/{id}` (`custom-maker_bids.jobs.update`). 보류·취소·삭제는 유지합니다.
- 관리자 입찰 목록에서 상세/수정, `/admin/maker-bids/bids/:id` 수정 화면. 금액·기간·메시지·상태. `PATCH /admin/bids/{id}` (`custom-maker_bids.bids.update`).
- 관리자 **설정** `/admin/maker-bids/settings`: 회원 메뉴 삽입 위치·라벨·레이아웃 확장 on/off, 페이지별 안내문, 등록 기본 상태, 비회원 목록 공개. 권한 `custom-maker_bids.settings.read` / `.update`.
- 설정 저장 테이블 `maker_module_settings` (additive). G7 `config/settings/defaults.json` 도 함께 둡니다. 공개 `GET /settings`, 관리자 `GET/PUT/PATCH /admin/settings`.
- 회원 화면 상단 안내문 자리(`data-cmb-notice`). 설정에서 켜고 본문을 넣으면 nav.js가 HTML을 채웁니다.

### Changed

- 버전 **0.7.0**. nav/form.js·form.css 캐시 `?v=0.7.0`.
- 0.6.1 소유자 목록/상세(`optional.sanctum`, `auth_mode: "optional"`)는 그대로입니다.

### Notes

- `php artisan module:update custom-maker_bids` 후 **캐시 삭제와 하드 리프레시**. 권한 동기화 필요(settings). 안내문·메뉴 위치는 설정 저장 후 회원 화면을 새로고침하세요.

## [0.6.1] - 2026-09-16

### Fixed

- 의뢰 상세에서 작성자·로그인 회원이 「의뢰를 찾을 수 없습니다.」를 보던 문제를 수정. `GET /jobs`·`GET /jobs/{id}`에 `optional.sanctum`을 붙이고, 목록/상세 data_source는 `auth_mode: "optional"`로 세션 토큰을 실어 보류·업체만/개인만 의뢰도 소유자·해당 회원이 열 수 있습니다. 비회원에게는 기존과 같이 숨깁니다.
- 공개 목록이 비어 보이던 문제를 수정. 로그인 작성자의 의뢰(보류 포함)는 목록·이력에 나오고, 다른 회원에게는 보류를 숨깁니다. `status=open`은 견적요청·의뢰·open을 모두 포함합니다. G7 배열 경로는 `jobs?.data?.data ?? jobs?.data`를 씁니다. 상태 Select 기본 옵션을 견적요청으로 둡니다.
- 등록/수정 성공 후 `/maker-bids/undefined`로 가던 문제를 수정. 응답을 `{ data: job, ...job }`로 맞추고 `{{response.data.id || response.id}}`로 숫자 id에 이동합니다.
- 없는 id는 `findOrFail` 대신 같은 404 문구의 `DomainException`을 씁니다. 권한 없는 이에게 존재 여부를 드러내지 않습니다.

### Changed

- 버전 **0.6.1**.

### Notes

- 레이아웃 반영은 `php artisan module:update custom-maker_bids` 후 **캐시 삭제와 하드 리프레시**가 필요합니다.

## [0.6.0] - 2026-09-16

### Fixed

- `/maker-bids/new` FileUploader PNG·ZIP 업로드가 `attachment.upload_response_invalid` 로 실패하던 문제를 수정. G7 `api.post`가 본문을 한 겹 풀기 때문에 Attachment를 `{ success, data: attachment, ...attachment }` 로 감싸고 HTTP 200을 반환합니다.
- 주문서 등록 시 「제목을 입력해 주세요」가 나오던 문제를 수정. 제목 Input을 `_local.form.title` 에 바인딩하고, 제출 직전에 `name` 필드를 harvest 하며 서버에서 nested `form` 을 펼칩니다.
- **임의입력**이 유형 Select를 채우지 않던 문제를 수정. 로드된 유형 카탈로그의 실제 `value`/`id` 를 고르고, 옵션을 클릭해 Select 표시 라벨까지 맞춥니다. 상태·예산·마감·크기·체크(급행/수정)·연락처·담당자·공개 설정도 채우며 이미지·파일 업로드는 건너뜁니다.

### Added

- 의뢰서 하단 **공개 설정**: 전체 / 업체만 / 개인만. `audience` 컬럼에 저장하고 목록·상세·입찰 자격을 맞춥니다. 승인 업체(`kind=company`)만 업체만 의뢰를 보고 입찰합니다. 개인(승인 업체가 아니거나 개인 등록)은 개인만 의뢰를 봅니다. 작성자·관리자·기존 입찰자는 제한과 무관하게 열람합니다.
- 제공 확장자에 **DWG** 추가. 모델링 포함 유형에서만 표시되는 기존 동작을 유지합니다.
- 제공 확장자 옆에 **소유권한 요청** 체크 하나(`ownership_requested`). 「저작권 있음」 별도 항목은 없습니다.
- **입찰자 등록** `/maker-bids/company`: 업체/개인, 주력유형, 로고(1장·최대 512×512), 소개, 홈페이지·포트폴리오 URL, 담당자, 다음 주소, 회원정보 자동입력. 관리자 보류/승인/거절·메모·평점·클레임·신고·추천·우선순위. 공개 업체·입찰 목록은 추천·우선순위 순.

### Changed

- 버전 **0.6.0**. nav/form.js·form.css 캐시 `?v=0.6.0`.

### Notes

- 레이아웃·CSS 반영은 `php artisan module:update custom-maker_bids` 후 **캐시 삭제와 하드 리프레시**가 필요합니다. `audience` / `ownership_requested` 및 입찰자 프로필 관리 컬럼이 없는 설치에는 additive 마이그레이션이 적용됩니다.

## [0.5.3] - 2026-09-16

### Fixed

- `/maker-bids/new` · `/maker-bids/:id/edit` 급행비 체크 시 **적용 조건 달력**(`datetime-local`)이 바로 아래에 나타나도록 수정. G7 `if`에만 의존하지 않고 필드를 항상 두고 CSS `:has()` / `form.js`로 표시·활성합니다. `rush_deadline`에 저장.
- 유형·상태 **Select**가 열리고 옵션을 고를 수 있게 수정. G7 커스텀 드롭다운은 `value` + `change` `setState`가 필요합니다. 빈 `[]` options(`||` 함정) 대신 `?.length`와 시드 폴백을 씁니다. 오버레이 `z-index` / `pointer-events` / `overflow: visible`도 맞춥니다.
- 수정 횟수 체크 시 **최소 횟수**(`revision_count`)와 **회당 / 최대 수정비용**(`revision_cost`) 입력이 보이게 수정. 기존 0.5.2 컬럼을 유지하고 라벨만 사용자 표현에 맞춥니다.

### Added

- 주문자명 옆 **회원정보 사용** 헬퍼(주간만과 동일). 체크 시 로그인 회원명 또는 승인 업체명을 채우고, 직접 수정하면 체크가 해제됩니다. DB 플래그는 없고 문자열만 저장합니다.
- 제공 확장자는 **모델링 포함 유형**에서만 표시. 유형 카탈로그 `includes_modeling` 플래그(시드: 3D 모델링·풀 패키지·커미션·워킹 프로토타입 = 켜짐, 3D 출력 대행·디자인 목업 = 꺼짐). 숨기면 제출 시 확장자를 비웁니다. 관리자 유형 화면에 체크박스가 있습니다.

### Changed

- 버전 **0.5.3**. nav/form.js·form.css 캐시 `?v=0.5.3`.

### Notes

- 레이아웃·CSS 반영은 `php artisan module:update custom-maker_bids` 후 **캐시 삭제와 하드 리프레시**가 필요합니다. `includes_modeling` 컬럼이 없는 설치에는 additive 마이그레이션이 적용됩니다.

## [0.5.2] - 2026-09-16

### Fixed

- `/maker-bids/new` · `/maker-bids/:id/edit` 다크 주문서에서 **제공 확장자**(STL/3MF/OBJ/STEP/STP/GCODE/FBX) 체크박스 레이블이 보이지 않던 대비 문제를 수정. 레이블을 `Label`+`Span`으로 명시하고 `form.css`에서 다크 텍스트 색을 강제합니다. 급행비·선점비·수정 횟수 체크박스(적용 가능/적용 유무)도 동일하게 맞춥니다. 테마는 유지하고 대비만 고칩니다.

### Added

- 연락 가능시간 옆 **주간만** 체크박스. 체크 시 `09:00 ~ 17:00` 자동 입력, 시각을 직접 바꾸면 체크 해제. UX 헬퍼이며 별도 DB 플래그는 없습니다.
- 급행비 체크박스 **바로 아래**에 적용 조건 `datetime-local` 달력. 체크 시에만 표시. 기존 `rush_deadline` 컬럼에 저장(없을 때만 마이그레이션 추가). 상세·관리자 화면에 조건 시각을 표시합니다.
- 수정 횟수 체크박스 **바로 아래**에 **최소 비용**·**몇 회 수정 가능** 입력. 체크 시에만 표시. 기존 `revision_cost` / `revision_count` 컬럼에 저장(없을 때만 마이그레이션 추가).
- **최종 크기** 행을 이름 + W × D × H(mm)로 확장. **추가**로 행을 늘리고(최대 20), 행마다 **삭제**(최소 1행 유지). JSON 컬럼 `sizes`에 저장하고, 첫 행은 기존 `size_w/d/h`에도 맞춥니다.
- 개인정보 하단 **담당자 정보** 카드: 담당자 명·연락처·이메일. 프로필이 없으면 빈 값. 다른 개인정보와 같이 낙찰 전까지 마스킹.
- **(임시)** `/maker-bids/new` 사이드 **임의입력** 버튼. 클릭 시 이미지·파일 업로드를 제외한 주문서 필드를 무작위 더미로 채웁니다(급행 켜면 조건 시각, 크기 1–3행, 담당자 정보 포함). **모듈 완성 후 삭제 예정.**

### Changed

- 버전 **0.5.2**. nav/form.js·form.css 캐시 `?v=0.5.2`.

### Notes

- 레이아웃·CSS 반영은 `php artisan module:update custom-maker_bids` 후 **캐시 삭제와 하드 리프레시**가 필요합니다. `rush_deadline` / `revision_*` / `sizes` / `manager_*` 컬럼이 없는 설치에는 additive 마이그레이션이 적용됩니다.

## [0.5.1] - 2026-09-16

### Changed

- `/maker-bids/new` · `/maker-bids/:id/edit` 주문서 카드를 테마에 맞게 다크 서페이스로 적용. 입력·셀렉트·체크박스·섹션 헤더·안내 문구·FileUploader 영역을 CSS 변수(`--color-card` 등)와 G7 다크 토큰(`dark:bg-gray-800/900`)으로 맞춥니다. 하얗게 떠 있던 `bg-white` / `dark:bg-zinc-*` 용지를 제거했습니다. 필드와 동작은 0.5.0과 동일합니다.
- 버전 **0.5.1**. nav/form.js·form.css 캐시 `?v=0.5.1`.

### Notes

- 레이아웃·CSS 반영은 `php artisan module:update custom-maker_bids` 후 **캐시 삭제와 하드 리프레시**가 필요합니다.

## [0.5.0] - 2026-09-16

### Added

- 의뢰 작성 `/maker-bids/new` 을 가운데 정렬 **주문서 카드**로 재구성. 제목·유형·예산 범위·마감·급행비·선점비·상태·크기·제공 확장자·수정 횟수/비용·설명·이미지·압축 파일·개인정보 블록.
- 유형 카탈로그 테이블 `maker_job_types` 및 시드 6종 (3D 모델링, 3D 출력 대행, 풀 패키지, 캐릭터·피규어, 디자인 목업, 워킹 프로토타입). 관리자 CRUD·정렬·활성/숨김 (`/admin/maker-bids/types`).
- 개인정보(주문자명/연락처/가능시간/이메일/주소)는 회원 프로필 기본값, 폼에서 수정. 주소는 다음 우편번호. 디자인 전용 유형은 주소 생략.
- 공개 목록/상세에서 개인정보는 **제작 의뢰 확정(낙찰)** 전까지 마스킹. 작성자·관리자·낙찰 상대만 열람. 압축 첨부도 동일.
- G7 FileUploader: 이미지는 jpg/png 등, 파일은 zip/tar/gz 등만. `apiEndpoints.upload` + `uploadTriggerEvent`. File을 setState에 넣지 않음.
- 회원 `PATCH /jobs/{id}`, 작성 기본값 `GET /jobs/form-defaults`, 업로드 `POST /uploads`, 유형 `GET /job-types`.
- 상태 보류/의뢰/견적요청. 보류는 공개 목록·상세에서 숨김. 수정 화면 `/maker-bids/:id/edit`.

### Changed

- 하드코드 `print_3d` / `design` / `manufacture` 를 DB 유형으로 교체. 기존 `design`→`design_mockup`, `manufacture`→`full_package`, `open`→`quote_request`.
- 버전 **0.5.0**. nav/form.js 캐시 `?v=0.5.0`.

### Notes

- **마이그레이션 필요**: `php artisan module:update custom-maker_bids` (또는 migrate) 후 **캐시 삭제와 하드 리프레시**.
- 새 테이블: `maker_job_types`, `maker_job_files`. `maker_jobs`에 예산 범위·급행·크기·개인정보 컬럼 추가.

## [0.4.0] - 2026-09-15

### Added

- 회원 UI: 의뢰 목록·작성·입찰현황·이력·상세·업체 등록. 빈 목록 안내(데모 시드 없음).
- 상세에서 견적 제출, 열린 의뢰의 **본인 입찰 금액 수정**, 의뢰 작성자 **낙찰** 버튼.
- 회원 업체 신청 `/maker-bids/company` (`GET/POST /companies`, 거절 시 재신청).
- 변경 API 버튼에 `auth_required`. 실패 시 toast (`{{error.message}}`).
- 관리자 메뉴 하위: 의뢰 목록 / 입찰 관리 / 회사 목록 / 회원 활동.
- 관리자 화면: 의뢰 보류·취소·삭제, 입찰 목록·필터·삭제, 업체 승인·거절·삭제.
- 레이아웃용 회원 API: `GET /jobs/mine`, `GET /bids/mine`, `GET /jobs/{id}/viewer`.
- 관리자 `POST /admin/jobs/{id}/cancel`, 의뢰 목록 `user_id` 필터.

### Changed

- 버전 **0.4.0**. 이력 탭은 로그인한 회원의 의뢰만 표시합니다.
- 헤더 nav.js 캐시 버스트 `?v=0.4.0`.

### Notes

- 0.3.0에서 마이그레이션은 이미 적용되어 있습니다. 레이아웃 반영은 `module:update` 후 **캐시 삭제와 하드 리프레시**가 필요합니다.

## [0.3.0] - 2026-09-15

### Added

- 의뢰 등록 검증: `type`(print_3d / design / manufacture), `title` 필수, `budget`/`closes_at`/`description` 선택.
- 회원 변경 API에 `auth:sanctum` 적용 (의뢰 등록, 입찰 생성/수정, 낙찰, 업체 신청).
- 입찰: 로그인 회원 또는 승인된 업체가 열린 의뢰에 입찰. 본인 입찰은 의뢰가 열려 있는 동안 수정 가능 (`PATCH /jobs/{id}/bids/{bidId}`). 기존 `POST /jobs/{id}/bids`는 본인 입찰이 있으면 같은 조건으로 수정.
- 낙찰: 의뢰 작성자만 가능. 선택된 입찰은 `accepted`, 나머지 입찰은 `rejected`, 의뢰는 `awarded`.
- 업체 신청 `POST /companies` 및 내 신청 `GET /companies/me`. 거절 건은 재신청 가능.
- 관리자 업체 거절 `POST /admin/companies/{id}/reject`.
- 관리자 입찰 API (`GET/PATCH/DELETE /admin/bids`, `GET /admin/bids/{id}`)와 의뢰 상세 `GET /admin/jobs/{id}`.
- 관리자 job / bid / company API에 `auth:sanctum` + `permission:admin,custom-maker_bids.{jobs|bids|companies}.{read|create|update|delete}`.
- 권한 카테고리 `bids`, `companies` 추가. 설치/업데이트 시 동기화.
- additive 마이그레이션: `maker_bids.company_id`, `(job_id, user_id)` unique, `maker_companies.user_id` unique, `rejected_reason`, `reviewed_at`.
- 도메인 규칙·라우트 계약 테스트 (`php tests/run.php`).

### Changed

- 공개 의뢰 목록이 비어 있어도 데모 데이터를 자동 넣지 않습니다.
- 의뢰 기본 제목 `새 의뢰` / 기본 유형 추정 제거. 잘못된 본문은 422.
- 버전 **0.3.0**.

### Security

- 게스트는 의뢰 등록·입찰·낙찰·업체 신청을 할 수 없습니다.
- 본인 의뢰에는 입찰할 수 없습니다.
- 관리자 변경 API는 Sanctum 인증과 모듈 permission 이 모두 필요합니다.
