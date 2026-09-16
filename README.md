# custom-maker_bid

Gnuboard 7 모듈. 회원 의뢰 / 회원·승인 업체 입찰 / 관리자 의뢰·입찰·업체·유형 관리.

버전 **0.5.2**. 의뢰 작성은 가운데 정렬 주문서 카드이며, 다크 샵 테마(네이비)에 맞춰 어두운 서페이스로 표시됩니다. 유형은 DB 카탈로그(시드 6종)입니다.

- 관리자: `/admin/maker-bid` `/admin/maker-bid/types` `/admin/maker-bid/bids` `/admin/maker-bid/companies` `/admin/maker-bid/activity`
- 회원: `/maker-bid` `/maker-bid/new` `/maker-bid/bids` `/maker-bid/history` `/maker-bid/company` `/maker-bid/{id}` `/maker-bid/{id}/edit`

홈 메뉴는 G7 메뉴 관리에 `의뢰/입찰` → `/maker-bid` 를 매뉴얼로 넣으면 됩니다.

## 설치 / 업그레이드 (0.5.2)

0.5.1에서 올라올 때 additive 마이그레이션이 있습니다(`rush_deadline`, `revision_*`, JSON `sizes`, `manager_*` 컬럼이 없으면 추가). 체크박스 레이블 대비·주간만·급행 조건 달력·크기 행 추가/삭제·담당자 정보·임시 임의입력이 포함됩니다. 레이아웃·CSS 반영은 모듈 업데이트 후 **캐시를 비우고 브라우저를 하드 리프레시**하세요.

```bash
php artisan extension:update-autoload
php artisan module:install custom-maker_bid
php artisan module:activate custom-maker_bid
# 이미 설치된 경우
php artisan module:update custom-maker_bid
php artisan cache:clear
```

테이블: `maker_jobs`, `maker_bids`, `maker_companies`, `maker_job_types`, `maker_job_files` (uninstall 시 dynamic tables로 정리).

## 권한

| identifier | 용도 |
|---|---|
| `custom-maker_bid.jobs.read` / `.update` / `.delete` | 관리자 의뢰·유형 (목록·보류·취소·삭제, 유형 CRUD) |
| `custom-maker_bid.bids.read` / `.update` / `.delete` | 관리자 입찰 |
| `custom-maker_bid.companies.read` / `.create` / `.update` / `.delete` | 관리자 업체 승인·거절·삭제 |

관리자 API는 `auth:sanctum` + 위 permission 미들웨어를 사용합니다. 모듈 업데이트 후 권한 동기화가 필요합니다.

관리자 메뉴 하위: **의뢰 목록** / **유형 관리** / **입찰 관리** / **회사 목록** / **회원 활동**.

## 공개 / 회원 API

Prefix: `/api/modules/custom-maker_bid`

| Method | Path | Auth | 설명 |
|---|---|---|---|
| GET | `/job-types` | 없음 | 사용 중인 의뢰 유형 |
| GET | `/jobs` | 없음 | 의뢰 목록. **보류 제외**. `type`, `status` 쿼리 |
| GET | `/jobs/{id}` | 없음 | 의뢰 상세. 개인정보는 확정 전 마스킹 |
| GET | `/files/{hash}` | 조건부 | 이미지 공개, 압축 파일은 확정 후 상대/작성자/관리자 |
| GET | `/jobs/mine` | sanctum | 내가 등록한 의뢰 |
| GET | `/jobs/form-defaults` | sanctum | 작성 폼 기본값 (프로필 + upload_token + types) |
| GET | `/jobs/{id}/viewer` | sanctum | 상세 UI 플래그 + 열람 가능한 개인정보 |
| POST | `/jobs` | sanctum | 의뢰 등록 |
| PATCH | `/jobs/{id}` | sanctum | 작성자 수정 (보류/의뢰/견적요청) |
| POST | `/uploads` | sanctum | FileUploader 스테이징 (collection=images\|archives) |
| POST | `/jobs/{id}/files` | sanctum | 기존 의뢰에 파일 첨부 |
| GET | `/bids/mine` | sanctum | 내 입찰 |
| POST | `/jobs/{id}/bids` | sanctum | 입찰 생성/수정 |
| PATCH | `/jobs/{id}/bids/{bidId}` | sanctum | 본인 입찰 수정 |
| POST | `/jobs/{id}/award` | sanctum | 낙찰. 이후 상대에게 개인정보 공개 |
| POST | `/companies` | sanctum | 업체 신청 |
| GET | `/companies/me` | sanctum | 내 업체 신청 |

입찰 가능: **로그인 회원** 또는 **승인된 MakerCompany**. 본인 의뢰에는 입찰 불가. 상태가 의뢰/견적요청이고 `closes_at` 이 없거나 미래일 때만 입찰/수정/낙찰.

의뢰 상태: `hold`(보류, 비공개) / `request`(의뢰) / `quote_request`(견적요청) / `awarded` / `done` / `cancelled`.

의뢰 등록 본문 예:

```json
{
  "title": "PLA 피규어 15cm 출력",
  "type": "print_3d",
  "budget_min": 10000,
  "budget_max": 100000,
  "closes_at": "2026-10-01 23:59:59",
  "status": "quote_request",
  "rush_fee_enabled": true,
  "rush_deadline": "2026-09-20T18:00",
  "schedule_premium_enabled": false,
  "sizes": [
    { "name": "본체", "w": 300, "d": 200, "h": 50 },
    { "name": "뚜껑", "w": 120, "d": 80, "h": 20 }
  ],
  "size_w": 300,
  "size_d": 200,
  "size_h": 50,
  "provided_extensions": ["STL", "3MF"],
  "revision_enabled": true,
  "revision_count": 2,
  "revision_cost": 10000,
  "description": "서포트 제거 포함",
  "contact_name": "홍길동",
  "contact_phone": "010-0000-0000",
  "contact_hours": "09:00 ~ 18:00",
  "contact_email": "user@example.com",
  "manager_name": "박지훈",
  "manager_phone": "010-8888-1111",
  "manager_email": "jihun.park@example.com",
  "zipcode": "06234",
  "address": "서울특별시 강남구 테헤란로 1",
  "upload_token": "…"
}
```

`type` 은 사용 중인 카탈로그 슬러그. 디자인 전용 유형(`is_design_only`)은 주소가 필수가 아닙니다.

## 관리자 API

모두 `auth:sanctum` + permission.

| Method | Path | Permission |
|---|---|---|
| GET | `/admin/jobs` | jobs.read (`type`, `status`, `user_id`) |
| GET | `/admin/jobs/{id}` | jobs.read (개인정보 포함) |
| PATCH | `/admin/jobs/{id}` | jobs.update |
| POST | `/admin/jobs/{id}/hold` | jobs.update |
| POST | `/admin/jobs/{id}/cancel` | jobs.update |
| DELETE | `/admin/jobs/{id}` | jobs.delete |
| GET/POST/PATCH/DELETE | `/admin/job-types` | jobs.read / jobs.update |
| POST | `/admin/job-types/{id}/move` | jobs.update (`direction`: up\|down) |
| GET | `/admin/bids` | bids.read (`job_id`, `user_id`, `status`) |
| GET | `/admin/bids/{id}` | bids.read |
| PATCH | `/admin/bids/{id}` | bids.update |
| DELETE | `/admin/bids/{id}` | bids.delete |
| GET | `/admin/companies` | companies.read (`status`) |
| POST | `/admin/companies` | companies.create |
| POST | `/admin/companies/{id}/approve` | companies.update |
| POST | `/admin/companies/{id}/reject` | companies.update |
| DELETE | `/admin/companies/{id}` | companies.delete |

## 테스트

G7 코어 없이 도메인 규칙, 라우트 계약, 레이아웃 JSON을 검증합니다.

```bash
php tests/run.php
```

## 식별자

| 항목 | 값 |
|------|-----|
| identifier | `custom-maker_bid` |
| vendor | `custom` |
| namespace | `Modules\\Custom\\MakerBid` |
| version | `0.5.2` |
