# custom-maker_bid

Gnuboard 7 모듈. 회원 의뢰 / 회원·승인 업체 입찰 / 관리자 의뢰·입찰·업체 관리.

버전 **0.4.0**. Phase 2는 회원·관리자 레이아웃을 0.3.0 인증 API에 연결합니다.

- 관리자: `/admin/maker-bid` `/admin/maker-bid/bids` `/admin/maker-bid/companies` `/admin/maker-bid/activity`
- 회원: `/maker-bid` `/maker-bid/new` `/maker-bid/bids` `/maker-bid/history` `/maker-bid/company` `/maker-bid/{id}`

홈 메뉴는 G7 메뉴 관리에 `의뢰/입찰` → `/maker-bid` 를 매뉴얼로 넣으면 됩니다.

## 설치 / 업그레이드 (0.4.0)

0.3.0에서 마이그레이션은 이미 끝났습니다. 레이아웃·메뉴를 받으려면 모듈 업데이트 후 **캐시를 비우고 브라우저를 하드 리프레시**하세요.

```bash
php artisan extension:update-autoload
php artisan module:install custom-maker_bid
php artisan module:activate custom-maker_bid
# 이미 설치된 경우
php artisan module:update custom-maker_bid
php artisan cache:clear
```

테이블: `maker_jobs`, `maker_bids`, `maker_companies` (uninstall 시 dynamic tables로 정리).

## 권한

| identifier | 용도 |
|---|---|
| `custom-maker_bid.jobs.read` / `.update` / `.delete` | 관리자 의뢰 (목록·보류·취소·삭제) |
| `custom-maker_bid.bids.read` / `.update` / `.delete` | 관리자 입찰 |
| `custom-maker_bid.companies.read` / `.create` / `.update` / `.delete` | 관리자 업체 승인·거절·삭제 |

관리자 API는 `auth:sanctum` + 위 permission 미들웨어를 사용합니다. 모듈 업데이트 후 권한 동기화가 필요합니다.

관리자 메뉴 하위: **의뢰 목록** / **입찰 관리** / **회사 목록** / **회원 활동**.

## 공개 / 회원 API

Prefix: `/api/modules/custom-maker_bid`

| Method | Path | Auth | 설명 |
|---|---|---|---|
| GET | `/jobs` | 없음 | 의뢰 목록. 데모 시드 없음. `type`, `status` 쿼리 |
| GET | `/jobs/{id}` | 없음 | 의뢰 상세 + 입찰 |
| GET | `/jobs/mine` | sanctum | 내가 등록한 의뢰 |
| GET | `/jobs/{id}/viewer` | sanctum | 상세 UI용 플래그 (`is_owner`, `can_bid`, `can_award`, `can_update_bid`, `my_bid`) |
| POST | `/jobs` | sanctum | 의뢰 등록. `title`, `type` 필수 |
| GET | `/bids/mine` | sanctum | 내 입찰 |
| POST | `/jobs/{id}/bids` | sanctum | 입찰 생성. 본인 입찰이 있고 의뢰가 open이면 수정 |
| PATCH | `/jobs/{id}/bids/{bidId}` | sanctum | 본인 입찰 수정 (의뢰 open, 입찰 pending) |
| POST | `/jobs/{id}/award` | sanctum | 의뢰 작성자만 낙찰. 나머지 입찰 거절 |
| POST | `/companies` | sanctum | 업체 신청 (`pending`) |
| GET | `/companies/me` | sanctum | 내 업체 신청 |

입찰 가능: **로그인 회원** 또는 **승인된 MakerCompany**. 본인 의뢰에는 입찰 불가. 의뢰 `status=open` 이고 `closes_at` 이 없거나 미래일 때만 입찰/수정/낙찰.

의뢰 등록 본문:

```json
{
  "title": "PLA 피규어 15cm 출력",
  "type": "print_3d",
  "description": "서포트 제거 포함",
  "budget": 35000,
  "closes_at": "2026-10-01 23:59:59"
}
```

`type`: `print_3d` | `design` | `manufacture`.

## 관리자 API

모두 `auth:sanctum` + permission.

| Method | Path | Permission |
|---|---|---|
| GET | `/admin/jobs` | jobs.read (`type`, `status`, `user_id`) |
| GET | `/admin/jobs/{id}` | jobs.read |
| PATCH | `/admin/jobs/{id}` | jobs.update |
| POST | `/admin/jobs/{id}/hold` | jobs.update |
| POST | `/admin/jobs/{id}/cancel` | jobs.update |
| DELETE | `/admin/jobs/{id}` | jobs.delete |
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
| version | `0.4.0` |
