# custom-maker_bids

Gnuboard 7 모듈. 회원 의뢰 / 회원·승인 업체 입찰 / 관리자 의뢰·입찰·업체·유형·설정 관리.

버전 **0.10.34**. 의뢰 작성·수정은 공유 `jobs_form` 주문서 카드이며, 다크 샵 테마(네이비)에 맞춰 어두운 서페이스로 표시됩니다. 유형은 DB 카탈로그(시드 6종)입니다. 입찰자 등록·공개 설정·소유권한 요청·관리자 의뢰/입찰 수정·모듈 설정(메뉴·안내문·입찰 허용·계좌이체 결제)이 포함됩니다.

- 관리자: `/admin/maker-bids` `/admin/maker-bids/types` `/admin/maker-bids/jobs/{id}` `/admin/maker-bids/bids` `/admin/maker-bids/bids/{id}` `/admin/maker-bids/companies` `/admin/maker-bids/ops` `/admin/maker-bids/disputes` `/admin/maker-bids/payments` `/admin/maker-bids/activity` `/admin/maker-bids/settings`
- 회원: `/maker-bids` `/maker-bids/new` `/maker-bids/bids` `/maker-bids/history` `/maker-bids/disputes` `/maker-bids/payments` `/maker-bids/company` `/maker-bids/companies` `/maker-bids/{id}` `/maker-bids/{id}/edit` `/maker-bids/{id}/work`

홈 메뉴는 설정에서 헤더 삽입을 켜 두거나, G7 메뉴 관리에 `의뢰/입찰` → `/maker-bids` 를 매뉴얼로 넣으면 됩니다.

## 설치 / 업그레이드 (0.9.0)

**Breaking (0.9.0):** identifier·권한·API·프론트 경로·에셋 URL을 **`custom-maker_bids`** 로 맞추고, PHP namespace를 **`Modules\Custom\MakerBids`** 로 바꿉니다. G7 `ExtensionManager::moduleIdentifierToNamespace` 는 identifier를 namespace로 만듭니다.

| identifier | G7 namespace | 이 버전 |
|---|---|---|
| `custom-maker_bids` | `Modules\Custom\MakerBids\` | `module.php` / composer / 레이아웃 권한 `custom-maker_bids.*` / nav 에셋 `/api/modules/custom-maker_bids/assets/...` 가 일치 |
| `custom-maker_bid` (0.8.3–0.8.5) | `Modules\Custom\MakerBid\` | 0.8.4/0.8.5는 권한·에셋 경로만 임시 id에 맞춘 응급 패치입니다 |

0.8.3–0.8.5 `custom-maker_bid` 설치를 **제거**하고 이 모듈을 `custom-maker_bids` 로 **재설치·활성화**하세요. 권한 키가 달라지므로 모듈 활성화로 `custom-maker_bids.*` 를 다시 동기화해야 합니다. DB 테이블(`maker_*`) 이름과 0.7–0.8 기능은 그대로입니다.

**관리자 설치:** 모듈 관리 → **수동 설치** → **GitHub**. 저장소 **전체 URL**을 넣으세요. G7은 GitHub에서 identifier만 입력해서 모듈을 찾지 않습니다.

`https://github.com/keidischoi/g7-module-custom-maker_bids`

레이아웃·CSS 반영은 모듈 설치 후 **캐시를 비우고 브라우저를 하드 리프레시**하세요.

```bash
php artisan extension:update-autoload
php artisan module:install custom-maker_bids
php artisan module:activate custom-maker_bids
# 권한이 안 맞으면 모듈을 비활성 후 다시 활성하거나, G7 권한 동기화를 실행하세요.
php artisan cache:clear
```

테이블: `maker_jobs`, `maker_bids`, `maker_companies`, `maker_job_types`, `maker_job_files`, `maker_module_settings`, `maker_payments` (uninstall 시 dynamic tables로 정리).

## 권한

| identifier | 용도 |
|---|---|
| `custom-maker_bids.jobs.read` / `.update` / `.delete` | 관리자 의뢰·유형 (목록·수정·보류·취소·삭제, 유형 CRUD) |
| `custom-maker_bids.bids.read` / `.update` / `.delete` | 관리자 입찰 (목록·수정·삭제) |
| `custom-maker_bids.companies.read` / `.create` / `.update` / `.delete` | 관리자 업체 승인·거절·삭제 |
| `custom-maker_bids.settings.read` / `.update` | 관리자 모듈 설정 (메뉴·안내문) |

관리자 API는 `auth:sanctum` + 위 permission 미들웨어를 사용합니다. 모듈 업데이트 후 권한 동기화가 필요합니다.

관리자 메뉴 하위: **의뢰 목록** / **유형 관리** / **입찰 관리** / **회사 목록** / **회원 활동** / **설정**.

## 설정 (안내문 / 메뉴)

`/admin/maker-bids/settings` 에서 저장합니다.

**회원 메뉴**
- 헤더 버튼(nav.js): 샵/홈 줄의 맨 뒤·맨 앞, 샵 뒤, 홈 뒤.
- 레이아웃 확장: `_user_base` / `home` 의 `header` append (`resources/extensions/*.json`). 테마 헤더 id가 `header`가 아니면 그 JSON의 `target_id`를 바꿔야 합니다.
- JS 삽입과 확장을 동시에 켜면 링크가 두 번 보일 수 있습니다. 보통은 JS만 켜 둡니다.

**안내문**
- 의뢰목록·작성·입찰현황·이력·입찰자 등록·상세·수정마다 표시 여부 + 본문.
- 회원 화면 상단 `data-cmb-notice` 자리에 렌더됩니다. HTML(문단·링크) 가능, script/iframe은 저장 시 제거됩니다.

**모듈 동작**
- 의뢰 등록 기본 상태 (견적요청 / 의뢰 / 보류).
- 비회원 의뢰목록 공개. 꺼도 로그인 작성자의 본인 의뢰는 0.6.1과 같이 목록·상세에 나옵니다.
- **입찰 허용 권한** (`bid_allow`): 모두 / 관리자 / 지정업체 / 모든 등록된 업체 / 등록된 개인회원 / 모든 등록된 업체 & 등록된 개인회원 / 일반회원. 게스트 입찰은 없어 **모두**는 로그인 회원 전체입니다. **일반회원**은 승인된 업체·개인 입찰자 등록이 없는 로그인 회원입니다. 의뢰 공개 설정(전체/업체만/개인만/관리자)과 함께 적용됩니다. 지정업체는 회사 목록에서 관리자가 표시한 승인 업체만 해당합니다. 관리자 입찰 수정 API는 이 제한을 타지 않습니다.
- **알림**: 이메일 / 사이트 알림(G7 홈페이지 벨). 모듈 「알림」 페이지는 별도 기록입니다.

## 공개 / 회원 API

Prefix: `/api/modules/custom-maker_bids`

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
| POST | `/uploads` | sanctum | FileUploader 스테이징 (collection=images\|archives\|logos) |
| POST | `/jobs/{id}/files` | sanctum | 기존 의뢰에 파일 첨부 |
| GET | `/bids/mine` | sanctum | 내 입찰 |
| POST | `/jobs/{id}/bids` | sanctum | 입찰 생성/수정 |
| PATCH | `/jobs/{id}/bids/{bidId}` | sanctum | 본인 입찰 수정 |
| POST | `/jobs/{id}/award` | sanctum | 낙찰. `bid_id`와 선택 `deposit_percent`/`deposit_terms`. 계약금·잔금 입금 안내 생성 |
| GET | `/payments` | sanctum | 내 계좌이체 (계약금/잔금 행) |
| POST | `/jobs/{id}/payment/report` | sanctum | 입금 신고 (`kind` 선택) |
| POST | `/jobs/{id}/payment/confirm` | sanctum | 입금 확인 (제작자 계좌일 때 낙찰자) |
| GET | `/companies` | 없음 | 공개 입찰자 목록 (추천·우선순위 순) |
| GET | `/settings` | 없음 | 공개 모듈 설정 (메뉴·안내문) |
| GET | `/companies/form-defaults` | sanctum | 입찰자 등록 기본값 |
| POST | `/companies` | sanctum | 입찰자 등록 |
| GET | `/companies/me` | sanctum | 내 입찰자 신청 |

입찰 가능: 설정 **입찰 허용 권한**과 의뢰 공개 설정을 모두 통과한 로그인 회원. 기본(모두)은 로그인 회원입니다. 본인 의뢰에는 입찰 불가. 상태가 의뢰/견적요청이고 `closes_at` 이 없거나 미래일 때만 입찰/수정/낙찰. 공개 설정이 업체만/개인만이면 대상만 목록에 보이고 입찰할 수 있습니다. 허용되지 않으면 한국어 403입니다.

**결제:** 기본은 계좌이체입니다. 입찰자 등록에 은행·계좌·예금주와 기본 계약금 비율(기본 30%)·조건을 넣습니다. 계좌번호는 본인·관리자에게만 보입니다. 낙찰 시 요청 비율 > 업체 기본 > 설정 기본 순으로 계약금·잔금이 나뉘고, 잔금은 계약금 확인 뒤에만 신고합니다. 완료는 모든 입금 확인 뒤입니다. 의뢰서에는 선택으로 환불 은행·계좌 소유자명·계좌번호를 넣을 수 있고, 낙찰된 제작자와 관리자에게만 보입니다.

의뢰 상태: `hold`(보류, 비공개) / `request`(의뢰) / `quote_request`(견적요청) / `awarded` / `done` / `cancelled`.
공개 설정 `audience`: `all`(전체) / `company`(업체만) / `individual`(개인만) / `admin`(관리자).

의뢰 등록 본문 예:

```json
{
  "title": "PLA 피규어 15cm 출력",
  "type": "print_3d",
  "budget_min": 10000,
  "budget_max": 100000,
  "closes_at": "2026-10-01 23:59:59",
  "status": "quote_request",
  "audience": "all",
  "ownership_requested": false,
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
  "provided_extensions": ["STL", "3MF", "DWG"],
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
| GET | `/admin/companies/{id}` | companies.read |
| POST | `/admin/companies` | companies.create |
| PATCH | `/admin/companies/{id}` | companies.update |
| POST | `/admin/companies/{id}/approve` | companies.update |
| POST | `/admin/companies/{id}/hold` | companies.update |
| POST | `/admin/companies/{id}/reject` | companies.update |
| DELETE | `/admin/companies/{id}` | companies.delete |
| GET | `/admin/settings` | settings.read |
| PUT/PATCH | `/admin/settings` | settings.update |

## 테스트

G7 코어 없이 도메인 규칙, 라우트 계약, 레이아웃 JSON을 검증합니다.

```bash
php tests/run.php
```

## 식별자

| 항목 | 값 |
|------|-----|
| identifier | `custom-maker_bids` |
| vendor | `custom` |
| namespace | `Modules\\Custom\\MakerBids` |
| github_url | `https://github.com/keidischoi/g7-module-custom-maker_bids` |
| version | `0.10.1` |
