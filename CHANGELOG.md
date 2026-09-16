# Changelog

이 프로젝트의 모든 주요 변경사항을 기록합니다.
형식은 [Keep a Changelog](https://keepachangelog.com/ko/1.1.0/)를 따르며,
[Semantic Versioning](https://semver.org/lang/ko/)을 준수합니다.

## [0.5.2] - 2026-09-16

### Fixed

- `/maker-bid/new` · `/maker-bid/:id/edit` 다크 주문서에서 **제공 확장자**(STL/3MF/OBJ/STEP/STP/GCODE/FBX) 체크박스 레이블이 보이지 않던 대비 문제를 수정. 레이블을 `Label`+`Span`으로 명시하고 `form.css`에서 다크 텍스트 색을 강제합니다. 급행비·선점비·수정 횟수 체크박스(적용 가능/적용 유무)도 동일하게 맞춥니다. 테마는 유지하고 대비만 고칩니다.

### Added

- 연락 가능시간 옆 **주간만** 체크박스. 체크 시 `09:00 ~ 17:00` 자동 입력, 시각을 직접 바꾸면 체크 해제. UX 헬퍼이며 별도 DB 플래그는 없습니다.
- 급행비 체크박스 **바로 아래**에 적용 조건 `datetime-local` 달력. 체크 시에만 표시. 기존 `rush_deadline` 컬럼에 저장(없을 때만 마이그레이션 추가). 상세·관리자 화면에 조건 시각을 표시합니다.
- **(임시)** `/maker-bid/new` 사이드 **임의입력** 버튼. 클릭 시 이미지·파일 업로드를 제외한 주문서 필드를 무작위 더미로 채웁니다(급행 켜면 조건 시각 포함). **모듈 완성 후 삭제 예정.**

### Changed

- 버전 **0.5.2**. nav/form.js·form.css 캐시 `?v=0.5.2`.

### Notes

- 레이아웃·CSS 반영은 `php artisan module:update custom-maker_bid` 후 **캐시 삭제와 하드 리프레시**가 필요합니다. `rush_deadline` 컬럼이 없는 설치에는 additive 마이그레이션이 적용됩니다.

## [0.5.1] - 2026-09-16

### Changed

- `/maker-bid/new` · `/maker-bid/:id/edit` 주문서 카드를 테마에 맞게 다크 서페이스로 적용. 입력·셀렉트·체크박스·섹션 헤더·안내 문구·FileUploader 영역을 CSS 변수(`--color-card` 등)와 G7 다크 토큰(`dark:bg-gray-800/900`)으로 맞춥니다. 하얗게 떠 있던 `bg-white` / `dark:bg-zinc-*` 용지를 제거했습니다. 필드와 동작은 0.5.0과 동일합니다.
- 버전 **0.5.1**. nav/form.js·form.css 캐시 `?v=0.5.1`.

### Notes

- 레이아웃·CSS 반영은 `php artisan module:update custom-maker_bid` 후 **캐시 삭제와 하드 리프레시**가 필요합니다.

## [0.5.0] - 2026-09-16

### Added

- 의뢰 작성 `/maker-bid/new` 을 가운데 정렬 **주문서 카드**로 재구성. 제목·유형·예산 범위·마감·급행비·선점비·상태·크기·제공 확장자·수정 횟수/비용·설명·이미지·압축 파일·개인정보 블록.
- 유형 카탈로그 테이블 `maker_job_types` 및 시드 6종 (3D 모델링, 3D 출력 대행, 풀 패키지, 캐릭터·피규어, 디자인 목업, 워킹 프로토타입). 관리자 CRUD·정렬·활성/숨김 (`/admin/maker-bid/types`).
- 개인정보(주문자명/연락처/가능시간/이메일/주소)는 회원 프로필 기본값, 폼에서 수정. 주소는 다음 우편번호. 디자인 전용 유형은 주소 생략.
- 공개 목록/상세에서 개인정보는 **제작 의뢰 확정(낙찰)** 전까지 마스킹. 작성자·관리자·낙찰 상대만 열람. 압축 첨부도 동일.
- G7 FileUploader: 이미지는 jpg/png 등, 파일은 zip/tar/gz 등만. `apiEndpoints.upload` + `uploadTriggerEvent`. File을 setState에 넣지 않음.
- 회원 `PATCH /jobs/{id}`, 작성 기본값 `GET /jobs/form-defaults`, 업로드 `POST /uploads`, 유형 `GET /job-types`.
- 상태 보류/의뢰/견적요청. 보류는 공개 목록·상세에서 숨김. 수정 화면 `/maker-bid/:id/edit`.

### Changed

- 하드코드 `print_3d` / `design` / `manufacture` 를 DB 유형으로 교체. 기존 `design`→`design_mockup`, `manufacture`→`full_package`, `open`→`quote_request`.
- 버전 **0.5.0**. nav/form.js 캐시 `?v=0.5.0`.

### Notes

- **마이그레이션 필요**: `php artisan module:update custom-maker_bid` (또는 migrate) 후 **캐시 삭제와 하드 리프레시**.
- 새 테이블: `maker_job_types`, `maker_job_files`. `maker_jobs`에 예산 범위·급행·크기·개인정보 컬럼 추가.

## [0.4.0] - 2026-09-15

### Added

- 회원 UI: 의뢰 목록·작성·입찰현황·이력·상세·업체 등록. 빈 목록 안내(데모 시드 없음).
- 상세에서 견적 제출, 열린 의뢰의 **본인 입찰 금액 수정**, 의뢰 작성자 **낙찰** 버튼.
- 회원 업체 신청 `/maker-bid/company` (`GET/POST /companies`, 거절 시 재신청).
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
- 관리자 job / bid / company API에 `auth:sanctum` + `permission:admin,custom-maker_bid.{jobs|bids|companies}.{read|create|update|delete}`.
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
