# Changelog

이 프로젝트의 모든 주요 변경사항을 기록합니다.
형식은 [Keep a Changelog](https://keepachangelog.com/ko/1.1.0/)를 따르며,
[Semantic Versioning](https://semver.org/lang/ko/)을 준수합니다.

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
