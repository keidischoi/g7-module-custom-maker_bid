# Changelog

이 프로젝트의 모든 주요 변경사항을 기록합니다.
형식은 [Keep a Changelog](https://keepachangelog.com/ko/1.1.0/)를 따르며,
[Semantic Versioning](https://semver.org/lang/ko/)을 준수합니다.

## [0.10.28] - 2026-09-18

### Fixed
- **본인 보류·임시저장**: 의뢰목록 API가 웹 세션 사용자를 읽지 못해 내 보류/임시저장이 숨겨지던 문제를 고칩니다. 본인 의뢰는 목록에 나오고, 보류·임시저장·분쟁조정 칩도 본인 관계일 때만 보입니다.

### Added
- 작성·수정 화면에 **임시저장** 버튼을 둡니다. 상태 선택에도 임시저장이 있습니다.
- **분쟁조정**을 상태 분류·관리자 필터/상태 선택·분쟁조정 버튼에 넣습니다.

### Changed
- 버전·캐시 버스트 **0.10.28**.

## [0.10.27] - 2026-09-18

### Fixed
- **의뢰목록 검색**: 입력 중 키 이벤트를 가로채지 않습니다. 글자는 자유롭게 지울 수 있고, **엔터** 또는 **검색** 버튼으로만 조회합니다. 네이티브 검색창을 G7 입력 바깥에 두어 리마운트 시에도 글자가 되살아나지 않게 합니다.

### Added
- 의뢰상태 필터에 **임시저장**, **분쟁조정**을 넣습니다. 본인 관련 의뢰가 있을 때만 보이고, 타인에게는 숨깁니다.

### Changed
- 버전·캐시 버스트 **0.10.27**.

## [0.10.26] - 2026-09-18

### Fixed
- **관리자 승인/보류 색**: G7가 클래스를 지워도 인라인 `!important`와 `data-cmb-kind`로 현재 상태를 칠합니다. 승인은 녹색, 보류는 노란색.
- **회사목록 필터**: G7 Select가 비어 보이던 칸을 네이티브 상태 셀렉트로 대체하고, `min-width: 0`에 접히지 않게 합니다.
- **의뢰목록 검색 백스페이스**: G7 Input을 `display:none`으로 숨기고 네이티브 검색창만 씁니다. URL에 검색어가 없으면 남은 G7 값(`1111…`)을 복사하지 않습니다.
- **정렬 빈 칸 + 검색 버튼**: 네이티브 검색/정렬/검색 버튼을 한 줄로 재배치합니다.
- **형식·의뢰상태 필터**: 1줄은 **형식**, 2줄은 **의뢰상태**. 검색어가 남아 카드가 줄어들던 경우를 함께 고칩니다.

### Changed
- 버전·캐시 버스트 **0.10.26**.

## [0.10.25] - 2026-09-18

### Fixed
- **관리자 회사/의뢰 목록 승인·보류 버튼**: 현재 상태만 칠합니다. 승인은 녹색, 보류는 노란색 배경이며, 나머지 버튼은 외곽선만 남깁니다. 클릭 시 눌림(scale) 효과가 있습니다.
- **관리자 의뢰 상세 중복 크기 입력**: 레거시 W/D/H 한 줄을 숨기고 크기 행 UI만 남깁니다.
- 관리자 의뢰 상세에 **PDF** 제공 확장자 체크박스를 추가합니다.

### Changed
- 버전·캐시 버스트 **0.10.25**.

## [0.10.24] - 2026-09-18

### Fixed
- **의뢰목록 검색 백스페이스**: G7 Input을 숨기고 네이티브 입력만 쓰며, 포커스 중에는 URL 값으로 덮지 않음.
- **유형·상태 필터 무반응**: 같은 경로 G7 `navigate` 대신 jobs API를 직접 조회해 카드를 갱신. 칩 클릭은 soft-nav에서 제외.
- **정렬 셀렉트 빈 화살표**: G7 Select 트리거를 숨기고 네이티브 `<select>`를 표시.
- **입찰현황 「견적 넣기」 큰 버튼**: `jobs_bids`에서는 인라인 토글을 유지하고, 카드는 세로 배치 + 작은 버튼.

### Changed
- 버전·캐시 버스트 **0.10.24**.

## [0.10.23] - 2026-09-18

### Fixed
- **의뢰목록 홀쪽한 카드**: `LayoutFileFixListener`가 모든 `.cmb-card-list`에 인라인 `repeat(10, …)` 그리드를 넣던 문제를 제거. 의뢰/입찰/이력은 반응형 **1 / 2 / 3열**, 입찰자 목록만 `cmb-company-gallery` **2~5열**.
- **검색이 페이저·썸네일을 덮어씀**: `search-fix.js`가 `per_page=50`으로 카드를 다시 그리던 경로를 없애고, 검색·정렬·상태 칩은 URL 쿼리 + G7 `navigate`로 처리.
- **상태 칩 값**: `open`/`draft` 대신 실제 상태(`pending`/`hold`/`request`/`quote_request`/`awarded`/`done`/`cancelled`).
- 상세 `viewer` API의 `debug_session` 디버그 문자열 제거.
- 버전·캐시 버스트가 0.10.19에 멈춘 채 module.json만 올라가던 불일치 해소.

### Added
- 의뢰 카드 썸네일, 입찰자 카드 원형 로고를 레이아웃 JSON에 직접 배치.
- 의뢰목록 「등록된 의뢰」 옆 상태 필터 칩.

### Changed
- 버전·캐시 버스트 **0.10.23**.

## [0.10.22] - 2026-09-18

### Fixed
- 의뢰목록 고정 5열을 반응형 1/2/3열로 완화. 중복 「의뢰 등록」 CTA 제거, pager 추가.

## [0.10.21] - 2026-09-18

### Fixed
- 고아 업로드를 의뢰에 연결해 수정 API가 기존 이미지를 반환. `existing-files.js` 주입.

## [0.10.20] - 2026-09-18

### Fixed
- 의뢰·업체 수정 화면에서 기존 이미지/첨부/로고가 보이게 함.

## [0.10.19] - 2026-09-18

### Fixed
- **견적 제출 로그인 토스트**: `jobs_show` 견적 제출·금액 수정 버튼에서 G7 `apiCall`/`actions`를 제거하고 `cmb-bid-submit` / `cmb-bid-update` 클래스로만 처리. `page.js`가 capture-phase로 `preventDefault`+`stopImmediatePropagation` 후 cookie+CSRF fetch. (G7 Button이 data-*를 DOM에 안 올려도 클래스 매칭.)
- **수정 화면 기존 파일 안 보임**: FileUploader `initialFiles`에만 의존하지 않고, 회원 `jobs_form`·관리자 `jobs_show`·`company_apply`·관리자 업체 수정에 **등록된 파일 갤러리**(`cmb-existing-files`)를 추가. `uploader_epoch` + `files_ready` 재토글로 업로더 리마운트 보강.

### Changed
- 버전·캐시 버스트 **0.10.19**.
- `jobs_show` 페이지 401 토스트 문구를 `{{error.message || '요청을 처리하지 못했습니다.'}}`로 완화.

## [0.10.18] - 2026-09-18

### Fixed
- **수정 화면 FileUploader 빈 박스**: G7 FileUploader는 `initialFiles`를 마운트 시에만 읽음. 회원 `jobs_form`·관리자 `jobs_show`·`company_apply`·관리자 `companies_index`에서 API/`불러오기` hydrate 후 `files_ready`로 업로더를 다시 마운트해 등록된 이미지·첨부·로고가 보이게 함.
- **Attachment 정규화**: `UploadRules::toUploaderFile` + `MakerJobFile::toAttachmentArray` / `CompanyPresenter::logoFiles`에 `file_name`/`name`/`url`/`download_url`/`thumbnail_url`/`is_image` 별칭 포함. JobPresenter는 `image`/`archive` 단수 컬렉션도 분류.
- **견적 제출 로그인 토스트**: 공개 의뢰 상세에서 `auth_required`가 세션 쿠키와 무관하게 클라이언트 401 토스트를 내던 문제. 견적/수정/낙찰/거절·compare는 `auth_mode: optional`로 바꾸고, `page.js`가 `data-cmb-bid-submit` 클릭을 cookie+CSRF POST로 처리. 서버 `auth:sanctum` 유지.

### Changed
- 버전·캐시 버스트 **0.10.18**.

## [0.10.17] - 2026-09-18

### Fixed
- **페이지 안내문**: `company_apply` / `company_list`에 `data-cmb-notice` 슬롯 추가. `SettingsRules::pages()`에 `companies`(입찰자 목록) 추가, 관리자 설정에 안내문 토글 복제. `nav.js`가 안내문을 캐시하고 SPA 이동(pushState/popstate/click) 시 0/80/300ms로 재적용.
- **견적 넣기 로그인 토스트**: `GET jobs/{id}/viewer`를 `optional.sanctum`으로 이동. 비로그인 시 `authenticated: false` / `can_bid: false` 반환(401 없음). 상세 로그인 안내에 `/login` 링크 추가. 입찰 POST는 `auth:sanctum` 유지.
- **수정 화면 업로더 기존 파일**: 회원 `company_apply`는 `me` 응답의 `logo_files`를 `_local.company.logo_files`에 넣고 `initialFiles`에 바인딩. 회원 `jobs_form`·관리자 `jobs_show`는 onSuccess에서 `image_files`/`archive_files`(+`upload_token`)를 로컬에 복사해 FileUploader `initialFiles`에 연결. 관리자 의뢰에 첨부(archives) 업로더 추가. `findForEdit`/`findAdmin`이 `upload_token`을 발급.

### Changed
- 버전·캐시 버스트 **0.10.17**.

## [0.10.16] - 2026-09-18

### Fixed
- **제공 확장자 체크박스 쓰레기 값(FALSE/KRW/단일문자)**: `g7Get`이 `settings.general` 등 잘못된 노드를 치면 `normalizeExtList`가 객체 **값**을 확장자로 오인하던 문제를 수정. 임의 객체 값은 더 이상 순회하지 않고, 확장자 맵(`STL: true`)만 **키**를 사용합니다.
- **`asExtToken` 강화**: 길이 < 2, 순수 숫자, TRUE/FALSE/YES/NO/ON/OFF/NULL/UNDEFINED, 통화·로케일(KRW/USD/KR/US/EN 등) 거부. 알려진 확장자 화이트리스트 밖이거나 오염되면 `EXT_FALLBACK`으로 대체.
- **명세(`page.js`)·PHP `UploadRules::parseExtensionList`**: 동일 거부 규칙. 연관 배열의 비확장자 맵은 값 리스트로 취급하지 않음.

### Changed
- 버전·캐시 버스트 **0.10.16**.

## [0.10.15] - 2026-09-18

### Fixed
- **의뢰 유형 `[object Object]`**: G7 Select가 option 객체를 `form.type`에 넣으면 `String(obj)` → `[object Object]`가 API로 전송되던 문제를 수정. `asTypeSlug`로 value/slug/id만 추출하고, 트리거·페이로드·카탈로그 전 경로에서 문자열 슬러그만 사용합니다.
- **제공 확장자 `[OBJECT` / `OBJECT]`**: `provided_extensions`가 객체/객체배열일 때 `String(obj).split` 하던 경로를 `normalizeExtList`/`asExtToken`으로 교체(Object.values, value|label|ext|slug). 명세(`page.js`)도 동일 정규화.
- **PHP 안전장치**: 유형이 배열이면 value/slug 추출, `[object Object]`/`Array` 문자열은 누락 처리 후 명확한 422. 확장자 항목이 객체/배열이면 문자열로 정규화.

### Changed
- 버전·캐시 버스트 **0.10.15**.

## [0.10.14] - 2026-09-18

### Fixed
- **의뢰 등록 상태 검증**: 한국어 라벨(견적요청 등)이 `status`로 전송되며 "선택한 상태이(가) 올바르지 않습니다."가 나던 문제를 수정. 클라이언트·`prepareForValidation`·`JobService`에서 slug(`quote_request` 등)로 정규화합니다.
- **더미 입력**: type/status/audience(공개 설정) 셀렉트 값 설정 + change 트리거, 제작 사양(sizes) 채우기, 제공 확장자 체크. 유형은 모델링(`modeling_3d`)으로 확장자 섹션이 열리게 함.
- **등록/저장 후 셀렉트 옵션 소실**: 폼 부팅 시 `form-defaults`·유형 카탈로그에서 옵션을 다시 로드(`reloadFormCatalog`).
- **제공 확장자 → 의뢰 명세**: `ext_*` 체크를 `provided_extensions` 배열로 합쳐 검증 전에 보존. form-defaults가 설정 기반 확장자 목록을 반환. 설정이 비면 STL/OBJ/3MF/FBX/PDF… 기본값.
- **제작 사양 랜덤 추가**: 「랜덤 추가」 버튼으로 sizes 행을 무작위 추가.
- **업체 이미지(로고) 업로드**: 회원 폼 `autoUpload` + logo_count, 승인/대기 상태에서도 폼이 보이면 저장 가능. 관리자 「불러오기」 시 `upload_token`·`logo_files` 주입.

### Changed
- 버전·캐시 버스트 **0.10.14**.

## [0.10.13] - 2026-09-18

### Fixed
- **의뢰 수정 저장 시 필드 초기화**: 이미지(FileUploader)만 바꾼 뒤 저장하면 예산·설명·공개설정·주소 등이 비던 문제를 수정. 업데이트 FormRequest는 `nullBlankFields` 대신 `dropBlankKeys`로 빈 값을 제거하고, `JobService::jobAttributes`는 null/빈 문자열로 기존 컬럼을 덮어쓰지 않습니다.
- **「이 유형은 주소가 필요합니다」 on edit**: 주소 미전송/빈 값일 때 기존 DB 주소를 유지하고, 주소 필수 검증도 기존 값으로 통과합니다. 소유자 수정 API는 개인정보(주소·연락처)를 항상 실값으로 내려 폼에 채웁니다.
- **업체 수정(회원·관리자)**: 로고만 저장해도 프로필이 지워지지 않도록 `applicantUpdateAttributes` + blank `business_no` 스킵. 기존 업체 PATCH는 `applyUpdateRules`(name/kind sometimes).
- **입찰 수정**: blank `days`/`message`가 기존 값을 null로 덮지 않음. 부분 업데이트 시 누락 키 유지.
- **의뢰 명세**: 카탈로그에 제목·예산 라벨·확장자 플래그·연락시간 from/to 보강. `JobPresenter`가 `contact_hours_from`/`to`를 내려 수정 폼 재바인딩.

### Changed
- 입찰 상태 `pending` 표시를 **검토중**으로 변경 (DB 값은 `pending` 유지).
- 버전·캐시 버스트 **0.10.13**.

### Added
- 의뢰·업체 폼 **더미 입력 (테스트)** 버튼 — `_local.form`/`company`에 한국어 샘플을 채우며 제출하지 않음.
- `tests/partial_update.php` 회귀 테스트.

## [0.10.12] - 2026-09-18

### Added
- 의뢰 **공개 설정**에 **관리자** 옵션 (`audience=admin`). 작성자·관리자만 열람, **관리자만 입찰** (기존 `BidRules::ALLOW_ADMIN` / 「관리자만」과 동일 취지). 목록에서는 비관리자에게 숨김(본인 의뢰 제외).
- 관리자 설정 **제공 확장자** (`general.provided_extensions` CSV). 의뢰 폼·명세 체크 목록이 이 설정을 사용 (기본 STL,3MF,OBJ,STEP,STP,GCODE,FBX,DWG). PDF 등 추가 가능.
- 관리자 **의뢰 수정** 이미지 업로드(FileUploader, 기존 job files API). 관리자는 타인 의뢰 파일 업로드·삭제 가능.
- 관리자 **업체 수정**·회원 **입찰자 등록/수정** 로고 업로드 보강 (`logo_files` · `upload_token` 보장).

### Changed
- 관리자 설정 **기본 입찰 공개 설정** (`bid_audience_mode`) 제거. 의뢰 폼 공개 설정은 항상 표시. 플랫폼 전역 **입찰 허용 권한** (`bid_allow`)은 유지.
- 의뢰 상세 **개인정보**를 의뢰 명세 그리드에서 분리해 별도 `cmb-section-card` / `cmb-privacy-card`로 표시 (마스킹 규칙 동일: 본인·관리자·낙찰자).

### Fixed
- 0.10.11 명세 키/입찰 권한 라벨 매핑 유지.

### Meta
- 버전·캐시 버스트 **0.10.12**.

## [0.10.11] - 2026-09-18

### Fixed
- **의뢰 명세 전부 해당없음**: 상세의 `data-cmb-job-spec`가 라우트 id 없는 `_local.form`(빈 의뢰 폼 셸)을 job으로 오인해 유형·예산·마감·급행·크기·설명 등을 전부 `해당없음`으로 그리던 문제를 수정. 상세에서는 **job.id가 URL과 일치**할 때만 사용하고, `payload`/`type_slug`/`rush_fee` 등 저장 키를 정규화한 뒤 CATALOG/FALLBACK이 읽습니다.
- **입찰 권한** 표시: 명세·목록에서 `job.audience` → **전체/업체만/개인만** (`audience_label`). 관리자 의뢰 목록 메타에 `입찰 권한` 컬럼 문구 추가.
- **본인 의뢰 개인정보**: 소유자(`viewer.is_owner` / `privacy_visible` / `can_view_privacy`)는 연락처 등을 `비공개`로 가리지 않음. (입찰 허용 권한·기본 입찰 공개 설정과는 무관 — PII 박스는 별도 privacy 규칙.)

### Changed
- 버전·캐시 버스트 **0.10.11**.

## [0.10.10] - 2026-09-17

### Added
- 관리자 설정 **기본 입찰 공개 설정** (`general.bid_audience_mode`: `admin_only` | `public`).
  - **공개**: 의뢰 작성/수정에 공개 설정 선택 표시 — **전체**(업체+개인) / **업체만** / **개인만** (`JobRules::AUDIENCES`).
  - **관리자만**: 회원 의뢰 폼에서 공개 설정 셀렉트 숨김. 생성 시 `audience=all` 강제, 수정 시 클라이언트 `audience` 무시(관리자 패널에서만 변경).
- 의뢰 폼 defaults API에 `bid_audience_mode` · `audience_selectable` 포함. `form.js`가 섹션 show/hide.
- 의뢰목록 **정렬**: `sort=latest|created|views` (최신순/등록순/조회순). UI 셀렉트·소프트 갱신.
- 의뢰 `view_count` 컬럼(마이그레이션) + 상세 조회 시 증가. 조회순 정렬에 사용.
- **입찰현황** 열린 의뢰 카드에서 **견적 넣기** 인라인 폼(금액·일수·메시지·제출). `POST …/jobs/{id}/bids` 후 `jobs`/`mine` refetch.

### Fixed
- **의뢰목록 검색**: 검색어가 `_local.search.q`에 바인딩되지 않아 `q=`가 비던 문제 수정. 입력 변경·검색 버튼·**Enter**가 동일하게 동작(전체 새로고침 없음).
- 검색 `q`가 제목·설명·유형·연락/담당자명·의뢰인 회원명/아이디·업체명까지 OR 매칭.

### Changed
- 버전·캐시 버스트 **0.10.10**.


## [0.10.9] - 2026-09-17

### Fixed
- **의뢰 명세 무한 로딩**: 상세의 `의뢰 명세를 불러오는 중입니다.` 가 끝나지 않던 문제를 수정. `G7Core.state` 키 조회 실패·SPA에서 job이 늦게 도착·MutationObserver가 `characterData`만 바뀔 때 재스캔하지 않던 경우를 모두 커버.
- 명세는 내장 필드 카탈로그로 **동기 렌더**하며, 상태 전체 walk → 필요 시 job API 직접 fetch → 폴링/`state.subscribe`로 호스트에 `data-cmb-job-spec-ready`가 붙을 때까지 재시도. 카탈로그 렌더가 실패해도 job에 있는 키로 폴백 표시.
- 빈 값 `해당없음`, 개인정보 마스킹, 이미지 갤러리(+N·라이트박스), 금액 천단위 포맷 유지.

### Changed
- 버전·캐시 버스트 **0.10.9**.

## [0.10.8] - 2026-09-17

### Fixed
- **관리자 흰 박스 강제 제거**: `.cmb-admin-card` / `.cmb-admin-row` / `.cmb-admin-nav` / filter·settings·ops 패널이 호스트 `bg-white`/`bg-card`/`bg-background`에 밀려 다시 흰색이 되던 문제를 수정. `html.dark` 감지에 의존하지 않고 관리자 표면을 항상 `rgba(255,255,255,0.06~0.08)` + `border rgba(…,0.12)` + 밝은 텍스트로 `!important` 강제. `admin.js`는 `.cmb-admin` 존재 시 `cmb-admin-dark`를 무조건 켜고 런타임 CSS로 호스트 유틸을 덮어씀.
- 공개 페이지도 `html.cmb-dark-boot`에서 `.cmb-section-card`/`.cmb-list-item` 흰 폴백을 반투명 카드로 덮음.

### Changed
- 버전·캐시 버스트 **0.10.8**.

## [0.10.7] - 2026-09-17

### Fixed
- **의뢰 상세 명세 누락**: 공개·관리자 의뢰 상세의 `의뢰 명세`가 의뢰 유형별 전체 의뢰서 필드를 렌더링하며, 값이 비어 있어도 항목을 생략하지 않고 `해당없음`으로 표시. 설명은 제목 영역의 별도 `의뢰 내용` 카드에서 제거하고 명세에만 표시.
- 연락처·배송지·담당자와 비공개 압축 파일은 기존과 동일하게 의뢰인·관리자·낙찰자에게만 실제 값을 표시하고, 그 외에는 `비공개`로 마스킹.
- **의뢰 명세 이미지**: 이미지 첨부가 있을 때만 명세 오른쪽에 대표 썸네일(+N 배지)을 표시하고, 클릭 시 이전/다음·키보드 이동이 되는 슬라이드 라이트박스로 탐색. 없으면 자리를 비움.
- **금액 천단위 구분**: 예산·입찰 금액 표시에 한국어 천단위 콤마(`100,000원`)를 적용. `amount_label`/`budget_label` 및 공통 `CMB.formatMoney` 사용.

### Changed
- 버전·캐시 버스트 **0.10.7**.

## [0.10.6] - 2026-09-17

### Fixed
- **관리자 다크 테마 흰 박스**: 의뢰 목록 행·필터 카드·운영(신고 종결) 폼 등 컨테이너가 `white`/`#fff`/`rgb(255…)`로 남던 문제를 수정. `html.dark`뿐 아니라 `data-theme="dark"` / `cmb-dark-boot` / 런타임 `cmb-admin-dark`(밝기 감지)에서도 `rgba(255,255,255,0.06~0.08)` 반투명 표면·`0.12` 테두리·`1rem` 라운드를 강제. `form.css`의 `.cmb-list-item`/`.cmb-section-card` 화이트 폴백이 관리자(`.cmb-page` 없음)에 먹히지 않게 덮어씀.

### Changed
- 버전·캐시 버스트 **0.10.6**.

## [0.10.5] - 2026-09-17

### Added
- Artisan **`maker-bids:seed-dummy-bids {jobId=5}`** — NAS/데모용 한국어 더미 입찰 시드 (필요 시 `[더미]` 업체 생성). `--fresh` 로 이전 시드 입찰 제거 후 재시드. `php82 artisan maker-bids:seed-dummy-bids 5`.

### Changed
- 버전·캐시 버스트 **0.10.5**.

## [0.10.4] - 2026-09-17

### Added
- 관리자 **의뢰 상세/수정** 툴바에 **승인** 버튼 추가 (`POST …/jobs/{id}/approve`, 목록과 동일 API).

### Changed
- 관리자 **승인 / 보류 / 거절** 버튼을 채워진 배경색으로 통일. 현재 상태 버튼은 진한 배경·링·「✓ … · 현재」로 강조(목록·상세·업체).
- 공개 **의뢰목록** 검색줄: 검색 입력과 **검색** 버튼을 한 줄(`cmb-search-bar`). 관리자 필터도 가능하면 한 줄 유지.
- 버전·캐시 버스트 **0.10.4**.

## [0.10.3] - 2026-09-17

### Fixed
- **의뢰서 수정** 시 유형 슬러그가 빈 값(`()`)으로 검증되던 오류. G7 Select harvest가 빈 값으로 `_local.form.type`을 덮어쓰지 않게 하고, 서버는 빈 `type`을 생략·`type_id`로 해석합니다.
- **업체 수정**(회원·관리자): 사업자번호 체크섬 강제 완화(10자리 형식), 빈 필드로 기존 값이 지워지지 않게 함, 승인 업체도 프로필 수정 가능.
- **관리자 목록** 다크 테마에서 행 카드(`cmb-admin-row`) 박스가 보이도록 대비·테두리 강화.
- **관리자 설정**: 체크박스/입력 바인딩 + 저장 전 harvest로 설정 저장이 동작하도록 연결.
- **의뢰 상세**: 의뢰서와 같은 공개 명세 섹션 추가. 개인정보는 본인·관리자·낙찰자만.
- **의뢰서 수정** UI/API는 **본인만** (`GET/PATCH …/jobs/{id}/edit` · `can_edit`). 관리자는 관리자 패널 사용.

### Changed
- 버전·캐시 버스트 **0.10.3**.

## [0.10.1] - 2026-09-17

### Added
- **1:1 쪽지 스레드**: 작업실 메시지 UX 강화(말풍선·역할 라벨). 외부 G7 쪽지/`g7_send_memo`/`MemoService` soft hook 유지·확장. 하드 모듈 없어도 낙찰 후 앱 내 대화 가능.
- **인쇄/PDF 문서**: 의뢰서·견적서 HTML 양식 export (`format=html` 또는 JSON `documents[].html`). 작업실에서 열어 인쇄→PDF 저장.
- **납품 파일 만료**: `delivery` 컬렉션 + `expires_at`(기본 30일), 만료 숨김/410, 스케줄 `purgeExpired`로 스토리지 비움(다운로드 로그 유지).

### Changed
- 낙찰자(제작자)도 낙찰/완료 후 아카이브·납품 파일 조회·업로드 가능. `done` 상태에서도 낙찰자 식별.
- 파일 다운로드 시 `maker_file_logs` 기록.
- 버전·캐시 버스트 **0.10.1**.

## [0.10.0] - 2026-09-17

### Added
- **알림**: 신규 입찰·낙찰·승인/보류·마감 임박/마감 시 사이트 알림(+ 메일/쪽지 soft hook).
- **마감 스케줄**: `getSchedules()` + `maker-bids:run-schedule` 시간당 마감 처리·마감 임박 알림.
- **작업실**: 제작중/출력중/발송(송장)/완료 + 납품 파일 업로드 UI·API.
- **완료·후기**: 평점/코멘트, 회사 `rating_score` 갱신, 의뢰·회사 후기 목록 API/UI.
- **입찰 비교**: 상세 비교 카드에서 낙찰/거절.
- **OPS/신뢰**: 임시저장(draft), 목록 검색, 약관 동의, 사업자번호 검증·중복 방지, 신고 종결 API/UI.

### Changed
- 버전·캐시 버스트 **0.10.0**.

## [0.9.15] - 2026-09-17

### Fixed

- 다크 샵에서 maker_bids 탭·목록 링크를 누를 때 새로고침이 하얗게 번쩍이던 FOUC를 줄입니다.
  - `form.css` 상단 + `nav.js` 부트 인라인 스타일로 `html`/`body`에 즉시 어두운 배경·`color-scheme: dark`를 칠합니다 (`cmb-theme` localStorage로 다음 로드에도 유지).
  - 같은 모듈 `/maker-bids` 링크(서브탭·카드)는 `G7Core.navigate` / `startViewTransition`으로 부드럽게 이동해 전체 크롬 화이트 플래시를 피합니다.
- 버전·캐시 버스트 **0.9.15**. `nav.js`는 FOUC 방지를 위해 sync 로드.

## [0.9.14] - 2026-09-17

- 다크 테마에서 목록 행·섹션 카드가 페이지 배경과 같아져 박스가 안 보이던 문제를 고칩니다. `.cmb-job-card` / `.cmb-section-card` 등에 명시적 `rgba(255,255,255,0.06)` 배경·`0.12` 테두리를 넣고, `page.js`가 `form.css`와 `cmb-list-item` 클래스를 보장합니다.

## [0.9.13] - 2026-09-17

- 공개 목록(의뢰·입찰현황·이력·입찰자·알림)에 `page`/`per_page` 페이지네이션과 카드형 섹션 레이아웃을 맞추고, 서브탭 오른쪽 정렬(0.9.12)과 함께 탭 전환 시 레이아웃이 흔들리지 않게 합니다. API 응답 `meta`: `total`, `page`, `per_page`, `last_page`.

## [0.9.12] - 2026-09-17

- 공개 maker_bids 서브탭을 모든 페이지에서 오른쪽 정렬로 통일해 탭 전환 시 위치가 흔들리지 않게 합니다.

## [0.9.11] - 2026-09-17

### Changed

- 회원(사용자) 홈·목록 화면의 **각 목록을 섹션 박스**(`cmb-section-card`)로 정렬합니다. 이력(알림·내 의뢰), 입찰현황(내 입찰·입찰 가능), 의뢰목록, 입찰자 목록·알림에 공통 헤더 행(`cmb-section-head`)과 안쪽 리스트 행 스타일을 맞춥니다.
- 버전·캐시 버스트 **0.9.11**.

## [0.9.10] - 2026-09-17

### Changed

- 공개 **의뢰서 작성/수정** 레이아웃을 `jobs_form` 하나로 통합합니다. `/maker-bids/new`·`/maker-bids/:id/edit` 모두 공유 레이아웃을 쓰며, `route.id`로 제목·안내문·업로더·제출(POST/PATCH)을 분기합니다. 수정 시 job 데이터소스 `auto_fetch`를 켭니다.
- 회원 **알림** 전용 라우트 `/maker-bids/notices` (`jobs_notices`)를 추가합니다. 이력 화면의 알림 패널과 동일 API(`GET/POST notices`)를 사용합니다.
- 버전·캐시 버스트 **0.9.10**.

### Left for later

- 관리자 **신고 종결** API·ops UI (클레임 종결만 연결됨; `resolveReport` 없음).

## [0.9.9] - 2026-09-17

### Changed

- 관리자 **공통 내비**를 `UserMenuListener`가 `#cmb_admin_nav` 스텁에 채워 넣도록 통일합니다. 의뢰·유형·입찰·회사·**운영**·활동·설정 7링크. `getAdminMenus`에도 운영 메뉴를 추가합니다.
- `/admin/maker-bids/ops` 를 클레임/신고/감사 로그 **카드+행 UI**로 재구성하고 클레임 종결/재오픈(`POST admin/claims/{id}`)을 연결합니다.
- 회사 편집·의뢰 상세 수정 폼을 카드/필드 그리드 패턴으로 정리합니다. 「색이 치며니다」 오타를 고칩니다.
- 작업실에 **메시지 작성**, **점수/후기 완료**, **클레임·신고** UI를 연결합니다. 이력에 **알림(notices)** 목록·읽음을 둡니다.
- marketplace extras(`maker_messages` 등)에 `Schema::hasTable` 가드와 `getDynamicTables` 등록을 보강합니다. migration 015 미적용 시 insert 500을 막습니다.
- 버전·캐시 버스트 **0.9.9**. `admin.js`의 `injectPortalCss` / `html.cmb-admin-ui` 를 복구합니다.

### Left for later

- 신고 관리자 종결 API (0.9.10에서 create/edit 통합·알림 라우트 완료).

## [0.9.8] - 2026-09-17

### Fixed

- **의뢰 상세** 상태 게이트가 `status === 'open'` 만 보던 문제를 고칩니다. 도메인 기본값(`quote_request` / `request`)에서도 잘못된 「열려 있지 않아」 배너가 뜨지 않도록 `job.is_open` / `viewer` 플래그를 사용합니다.
- **입찰자 목록**이 `/companies/me` 만 쓰던 문제를 고칩니다. 공개 `GET /companies` 디렉터리 + 내 등록 상태 카드로 분리합니다.
- 낙찰 후 **작업실**(`/maker-bids/{id}/work`) 링크를 상세·이력·입찰현황·낙찰 성공 네비에 연결합니다. `JobPresenter`에 `work_status` / `tracking_no` / `carrier` / `is_open` 을 노출합니다.

### Changed

- 공개 페이지(목록·상세·입찰·이력·입찰자·작업실)를 카드/배지/필터 칩 레이아웃으로 통일합니다. `form.css`의 `--cmb-*` 토큰을 확장한 `.cmb-page` / `.cmb-job-card` / `.cmb-badge` 를 사용합니다.
- 작성 화면 TEMP **임의입력** QA 버튼·스크립트·CSS를 제거합니다.
- 버전·캐시 버스트를 **0.9.8** 로 맞춥니다 (`module.json`, README, CHANGELOG, `UserMenuListener`, `form.js`, `cmb_maker_nav`).
- 공개 레이아웃 전반에 form.css를 주입하고, 서브 내비에 **입찰자 목록**을 공통으로 둡니다.

### Left for later (admin / P1)

- 관리자 ops 메뉴·resolve UI, create/edit 레이아웃 통합, 작업실 메시지 작성·클레임 UI, marketplace extras `getDynamicTables` / hasTable 가드.

## [0.9.6] - 2026-09-16

### Fixed

- 회원 **입찰자 등록** (`/maker-bids/company`) **등록** 버튼을 제목 줄이 아니라 서브 내비 줄 오른쪽 끝으로 옮깁니다. `의뢰서 작성 / 의뢰목록 / 입찰현황 / 이력 / 입찰자 등록 / 등록` 이 한 가로 밴드입니다. 0.9.5의 `cmb-company-title-row` 제목 옆 배치는 서브 내비 위에 흰 버튼이 떠 빈 간격이 생겼습니다.
- **등록** 클릭이 상태 카드(보류 목록)만 남기던 문제를 고칩니다. 입력 카드는 G7 `if`(상태 없음·거절만)로 pending에서 DOM에서 빠졌고, 버튼은 바로 POST 했습니다. 이제는 카드를 항상 두고, 보류는 접었다가 **등록**에서 업체/개인 입력 폼을 열고 포커스합니다. 처음 신청·거절은 폼이 보이며 **등록**이 제출합니다. 승인은 이전처럼 버튼·폼을 숨깁니다.
- 메인 **의뢰/입찰** 과 서브 내비 알약의 흰 활성 배경이 다른 메뉴로 이동한 뒤에도 남는 문제를 고칩니다. `nav.js`가 현재 경로만 `cmb-nav-current` / `cmb-tab-current` 로 맞추고, SPA `pushState`·클릭 후 포커스/`:active` 크롬을 지웁니다.

### Changed

- 버전 **0.9.6**. nav/form.js·form.css·admin.css·admin.js 캐시 `?v=0.9.6`.
- identifier·권한·API prefix `custom-maker_bids`, namespace `Modules\Custom\MakerBids` 는 변경하지 않습니다.

## [0.9.5] - 2026-09-16

### Fixed

- 관리자 **목록 행**을 한 줄 둥근 카드로 맞춥니다. G7 `Div`가 column으로 쌓아 제목·메타·버튼이 세로로 나뉘던 문제를 `flex-direction: row` + nowrap으로 고칩니다. `#id 제목 · 메타 [상세] [보류] [취소] [삭제]` 가 한 줄이고, 제목은 길면 ellipsis, 버튼은 오른쪽입니다. 640px 이하에서만 버튼이 감쌉니다.
- 대상: `jobs_index` · `bids_index` · `companies_index` · `types_index` · `activity_index` · 의뢰 상세 입찰 행. 다크 테마 테두리 대비(0.9.2)는 유지합니다.
- 관리자 **Select** 너비. 0.9.4의 `.cmb-admin-select` / `body:has` / `--radix-select-trigger-width` 는 라이브 G7 DOM(옵션 모드 커스텀 드롭다운·포털 메뉴)에 맞지 않아 닫힌 트리거가 줄어들고, 열린 「입찰 허용 권한」이 한글 한 글자씩 줄바꿈됐습니다. 이번에는 Select를 `cmb-admin-select-host` Div로 감싸 트리거 너비를 레이아웃이 보장하고, `admin.js`가 실제 button/listbox에 nowrap·min-width를 직접 넣습니다.
- 대상: 설정(기본 상태·입찰 허용·메뉴 위치), 목록 필터, 의뢰·입찰·업체 상세의 모든 관리자 Select. 필터 필드 너비(유형 ~14rem, 상태 ~12rem, ID ~8.5rem)는 0.9.2와 같습니다. 열린 메뉴만 옵션 텍스트에 맞게 넓어집니다.
- 회원 **입찰자 등록** (`/maker-bids/company`) 제목 줄 오른쪽 **등록** 버튼이 안 보이던 문제를 고칩니다. G7 `if` (`!me.data?.status || rejected`) 와 column `Div` 때문에 첫 방문·재신청·승인 대기에서 제출 컨트롤이 사라지거나 제목 아래로 밀렸습니다. `cmb-company-title-row` 로 한 줄 오른쪽 정렬을 강제하고, 레이아웃 `if` 를 제거해 버튼은 항상 그립니다. `form.js` 는 승인된 경우에만 숨깁니다. 하단 중복 제출은 두지 않습니다.

### Changed

- 버전 **0.9.5**. nav/form.js·form.css·admin.css·admin.js 캐시 `?v=0.9.5`.
- identifier·권한·API prefix `custom-maker_bids`, namespace `Modules\Custom\MakerBids` 는 변경하지 않습니다.
- 0.9.3 목록 카드 PR(#12)은 main(0.9.4) 기준으로 다시 구현해 이 버전에 포함합니다. #12는 대체됩니다.

## [0.9.4] - 2026-09-16

### Fixed

- 관리자 **Select** 드롭다운이 선택된 라벨 너비로 줄어 옵션이 줄바꿈되던 문제를 수정합니다. 「보류 (비공개)」, 「모든 등록된 업체 & 등록된 개인회원」 등이 한 줄로 보이도록 트리거와 열린 listbox(포털 포함)를 라벨보다 조금 넓게 맞춥니다.
- 대상: 설정·의뢰/입찰/업체 목록 필터·의뢰·입찰·업체 상세의 모든 관리자 Select. 목록 필터 필드 너비(유형 ~14rem, 상태 ~12rem, ID ~8.5rem)는 0.9.2와 같습니다.
- `resources/assets/admin.css` (form.css 폴백). 레이아웃에 `cmb-admin-select` 클래스를 붙입니다.

### Changed

- 버전 **0.9.4**. nav/form.js·form.css·admin.css 캐시 `?v=0.9.4`.
- identifier·권한·API prefix `custom-maker_bids`, namespace `Modules\Custom\MakerBids` 는 0.9.2와 동일합니다.
- 0.9.3은 관리자 목록 한 줄 카드 PR이 열려 있어 이 패치가 건너뜁니다.

## [0.9.2] - 2026-09-16

### Fixed

- 관리자 **목록** 화면이 왼쪽 한쪽에만 붙고 필터 너비가 깨지던 문제를 수정합니다. 목록 행은 콘텐츠 영역 **전체 너비**(`w-full`, 페이지 max-width 제거). 필터는 용도별 너비(유형 ~14rem, 상태 ~12rem, ID ~8.5rem, 버튼 auto). G7 Input/Select 래퍼가 `w-full` 이거나 트리거가 내용 너비인 경우를 래퍼 클래스로 맞춥니다.
- 다크 관리자 테마에서 목록 행·필터 카드 테두리가 배경에 묻히지 않도록 대비를 올립니다 (다크 gray-500/400, 라이트 gray-300/400). 행 사이 간격도 조금 띄웁니다.
- 대상: `jobs_index` · `bids_index` · `companies_index` · `types_index` · `activity_index`. 탭·제목은 유지합니다.
- `resources/assets/admin.css` 를 `_admin_base` 에 주입합니다 (`UserMenuListener`). form.css 관리자 폴백도 페이지 max-width 72rem을 제거합니다.

### Changed

- 버전 **0.9.2**. nav/form.js·form.css·admin.css 캐시 `?v=0.9.2`.
- identifier·권한·API prefix `custom-maker_bids`, namespace `Modules\Custom\MakerBids` 는 0.9.1과 동일합니다.

## [0.9.1] - 2026-09-16

### Fixed

- 설치 전 orphan `g7_menus` 를 정리합니다.

## [0.9.0] - 2026-09-16

### Changed

- **완전한 identifier 정렬 (breaking)**: `custom-maker_bids` + PHP namespace **`Modules\Custom\MakerBids`**. G7 `ExtensionManager::moduleIdentifierToNamespace` 가 `custom-maker_bids` → `MakerBids` 로 매핑합니다.
- 0.8.4/0.8.5는 identifier가 `custom-maker_bid` 인 채 관리자 권한·nav 에셋 경로만 임시 id에 맞춘 응급 패치입니다. 0.9.0은 권한 **`custom-maker_bids.*`**, 에셋 `/api/modules/custom-maker_bids/assets/nav.js` (및 form.js/css), 라우트 `/maker-bids` `/admin/maker-bids` 를 namespace와 함께 맞춥니다. 반쪽 권한/에셋 상태는 남기지 않습니다.
- composer `custom/maker-bids`, `github_url` `https://github.com/keidischoi/g7-module-custom-maker_bids`.
- **재설치 필요.** `custom-maker_bid` 설치를 제거하고 `custom-maker_bids` 로 설치·활성화해 권한을 다시 동기화하세요. dual-id 호환은 없습니다.
- 0.7–0.8 기능은 유지합니다.
- 버전 **0.9.0**. nav/form.js·form.css 캐시 `?v=0.9.0`.
- DB 테이블 이름(`maker_*`)은 그대로입니다. 모델 클래스 `MakerBid` 이름은 유지하고 namespace만 `MakerBids` 입니다.

## [0.8.5] - 2026-09-16

### Fixed

- **응급:** `cmb_maker_nav` / `UserMenuListener` / nav.js / form.js / 회원·관리자 라우트 에셋·경로를 `custom-maker_bid` 에 맞춰, 임시 identifier에서 nav 에셋이 로드되게 합니다. 정식 정렬은 0.9.0입니다.

## [0.8.4] - 2026-09-16

### Fixed

- **응급:** 관리자 레이아웃·`module.php`·API 권한 문자열을 `custom-maker_bid.*` 로 맞춰 「레이아웃 접근 권한이 없습니다」 403을 막습니다. 정식 정렬은 0.9.0입니다.

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
