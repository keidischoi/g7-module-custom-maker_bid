(function () {
  function path() {
    return String(location.pathname || '').replace(/\/+$/, '') || '/';
  }
  function dispatch(handler, params) {
    if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
      window.G7Core.dispatch({ handler: handler, params: params || {} });
    }
  }
  function setLocal(map) {
    var params = { target: 'local' };
    var key;
    for (key in map) {
      if (Object.prototype.hasOwnProperty.call(map, key)) params[key] = map[key];
    }
    dispatch('setState', params);
  }

  function collectCreateJobPayload() {
    if (window.cmbForm && typeof window.cmbForm.collectCreateJobPayload === 'function') {
      return window.cmbForm.collectCreateJobPayload();
    }
    return null;
  }
  function applyDummyJobForm() {
    if (window.cmbForm && typeof window.cmbForm.applyDummyJobForm === 'function') {
      return window.cmbForm.applyDummyJobForm();
    }
  }
  function syncProvidedExtOptions() {
    if (window.cmbForm && typeof window.cmbForm.syncProvidedExtOptions === 'function') {
      return window.cmbForm.syncProvidedExtOptions();
    }
  }
  function g7Get(p) {
    try {
      if (window.G7Core && window.G7Core.state && typeof window.G7Core.state.get === 'function') {
        return window.G7Core.state.get(p);
      }
    } catch (e) {}
    return null;
  }
  function unwrap(j) {
    var d = j;
    var i;
    for (i = 0; i < 4; i++) {
      if (d && d.data !== undefined && !Array.isArray(d.data) && typeof d.data === 'object') d = d.data;
      else break;
    }
    return d && typeof d === 'object' ? d : {};
  }
  function usable(d) {
    return !!(d && (d.id || d.title || d.name || d.type || d.phone || d.email));
  }
  function jobIdFromPath() {
    var m = path().match(/\/maker-bids\/(?:jobs\/)?(\d+)(?:\/edit)?$/);
    return m ? m[1] : '';
  }
  function isUserEdit() { return /\/maker-bids\/\d+\/edit$/.test(path()); }
  function isUserShow() { return /\/maker-bids\/\d+$/.test(path()) && path().indexOf('/admin/') < 0; }
  function isUserCompany() { return /\/maker-bids\/company$/.test(path()); }
  function isAdminJob() { return /\/admin\/maker-bids\/jobs\/\d+$/.test(path()); }
  function isAdminCompany() { return /\/admin\/maker-bids\/companies$/.test(path()); }

  function fillNamed(name, value) {
    document.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
      if (!el || el.type === 'file') return;
      if (el.type === 'checkbox') {
        el.checked = !!(value && value !== '0' && value !== 'false');
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return;
      }
      var tag = (el.tagName || '').toUpperCase();
      var proto = tag === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
      var desc = Object.getOwnPropertyDescriptor(proto, 'value');
      var str = value == null ? '' : String(value);
      if (desc && desc.set) desc.set.call(el, str); else el.value = str;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  function applyPrefixed(prefix, d, keys, bools) {
    var map = {};
    (keys || []).forEach(function (k) {
      var v = d[k];
      if (k === 'closes_at' && d.closes_at_local) v = d.closes_at_local;
      if (v == null) v = '';
      map[prefix + '.' + k] = v;
      fillNamed(k, v);
    });
    (bools || []).forEach(function (k) {
      map[prefix + '.' + k] = !!d[k];
      fillNamed(k, !!d[k]);
    });
    if (d.sizes) map[prefix + '.sizes'] = typeof d.sizes === 'string' ? d.sizes : JSON.stringify(d.sizes);
    if (d.sizes_json) map[prefix + '.sizes'] = d.sizes_json;
    setLocal(map);
  }

  function applyJob(d) {
    if (d && d.status != null && window.cmbForm && typeof window.cmbForm.normalizeStatusSlug === 'function') {
      d.status = window.cmbForm.normalizeStatusSlug(d.status);
    } else if (d && d.status) {
      var sm = { '견적요청': 'quote_request', '의뢰': 'request', '보류': 'hold', '임시저장': 'draft', open: 'quote_request' };
      if (sm[d.status]) d.status = sm[d.status];
    }
    applyPrefixed('form', d, [
      'title','type','status','audience','budget_min','budget_max','description',
      'closes_at','rush_deadline','size_w','size_d','size_h',
      'contact_name','contact_phone','contact_email','zipcode','address','address_detail',
      'manager_name','manager_phone','manager_email','revision_count','revision_cost'
    ], [
      'rush_fee_enabled','schedule_premium_enabled','revision_enabled','ownership_requested',
      'ext_stl','ext_3mf','ext_obj','ext_step','ext_stp','ext_gcode','ext_fbx','ext_dwg'
    ]);
  }
  function applyUserCompany(d) {
    applyPrefixed('company', d, [
      'kind','name','business_no','bio','homepage_url','portfolio_url',
      'manager_name','phone','email','zipcode','address','address_detail'
    ], []);
  }
  function applyAdminCompany(d) {
    applyPrefixed('edit', d, [
      'id','status','admin_memo','hold_reason','rating_score','rating_count',
      'claim_count','claim_history','report_count','priority','rejected_reason'
    ], ['is_recommended','is_designated']);
  }

  function fromState(ids) {
    var i, d;
    for (i = 0; i < ids.length; i++) {
      d = unwrap(g7Get(ids[i]));
      if (usable(d)) return d;
    }
    return null;
  }

  var JOB_KEYS = ['job.data','job','_data.job.data','_data.job','dataSources.job.data','dataSources.job'];
  var ME_KEYS = ['me.data','me','_data.me.data','_data.me'];

  function prefillUserEdit() {
    if (!isUserEdit()) return;
    var d = fromState(JOB_KEYS);
    if (usable(d)) applyJob(d);
  }
  function prefillAdminJob() {
    if (!isAdminJob()) return;
    var d = fromState(JOB_KEYS);
    if (usable(d)) applyJob(d);
  }
  function prefillUserCompany() {
    if (!isUserCompany()) return;
    var d = fromState(ME_KEYS);
    if (usable(d)) applyUserCompany(d);
  }
  function bindCompanyLoad() {
    if (!isAdminCompany()) return;
    if (document.documentElement.getAttribute('data-cmb-admin-co')) return;
    document.documentElement.setAttribute('data-cmb-admin-co', '1');
    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('button') : null;
      if (!btn || (btn.textContent || '').indexOf('불러오기') === -1) return;
      var row = btn.closest('.cmb-admin-row');
      var title = row && row.querySelector('.cmb-admin-row-title');
      var m = title ? String(title.textContent || '').match(/#(\d+)/) : null;
      if (!m) return;
      var list = fromState(['companies.data','companies','_data.companies.data']);
      var rowd = null;
      if (Array.isArray(list)) {
        list.forEach(function (c) { if (c && String(c.id) === m[1]) rowd = c; });
      }
      if (rowd) applyAdminCompany(rowd);
    }, true);
  }

  function run() {
    prefillUserEdit();
    prefillAdminJob();
    prefillUserCompany();
    bindCompanyLoad();
  }
  run();
  document.addEventListener('DOMContentLoaded', run);
  setTimeout(run, 200);
  setTimeout(run, 600);
  setTimeout(run, 1500);
  setTimeout(run, 3000);
})();


/* cmb-job-spec: render every applicable request-form field, including empty values */
(function () {
  var NONE = '해당없음';
  var PRIVATE = '비공개';
  var JOB_KEYS = ['job.data', 'job', '_data.job.data', '_data.job', 'dataSources.job.data', 'dataSources.job'];
  var VIEWER_KEYS = ['viewer.data', 'viewer', '_data.viewer.data', '_data.viewer', 'dataSources.viewer.data', 'dataSources.viewer'];

  function getState(key) {
    try {
      if (window.G7Core && window.G7Core.state && typeof window.G7Core.state.get === 'function') {
        return window.G7Core.state.get(key);
      }
    } catch (e) {}
    return null;
  }

  function unwrap(value) {
    var out = value;
    var i;
    for (i = 0; i < 6; i++) {
      if (out && out.data !== undefined && !Array.isArray(out.data) && typeof out.data === 'object') {
        out = out.data;
      } else {
        break;
      }
    }
    return out && typeof out === 'object' ? out : null;
  }

  function jobIdFromLocation() {
    var p = String(location.pathname || '');
    var m = p.match(/\/maker-bids\/(?:jobs\/)?(\d+)(?:\/|$)/) || p.match(/\/admin\/maker-bids\/jobs\/(\d+)/);
    return m ? String(m[1]) : '';
  }

  function audienceLabel(raw) {
    var v = String(raw == null ? '' : raw).toLowerCase().trim();
    if (v === 'company' || v === 'company_only' || v === '업체만' || v === '업체') return '업체만';
    if (v === 'individual' || v === 'individual_only' || v === '개인만' || v === '개인' || v === 'person') return '개인만';
    if (v === 'admin' || v === 'admin_only' || v === '관리자' || v === '관리자만') return '관리자';
    if (v === 'all' || v === '전체' || v === '') return '전체';
    return String(raw);
  }

  function jobRichness(job) {
    if (!job || typeof job !== 'object') return 0;
    var score = 0;
    if (job.id != null) score += 50;
    ['title', 'type', 'type_name', 'type_slug', 'budget_min', 'budget_max', 'budget', 'budget_label',
      'closes_at', 'closes_at_local', 'description', 'audience', 'audience_label', 'size_label',
      'sizes', 'rush_fee_enabled', 'status_label', 'privacy_visible'].forEach(function (k) {
      var v = job[k];
      if (v === null || v === undefined) return;
      if (typeof v === 'string' && v.trim() === '') return;
      if (Array.isArray(v) && !v.length) return;
      score += 1;
    });
    return score;
  }

  function normalizeJob(raw) {
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
    var base = raw;
    // Create/edit and some hosts nest saved fields under payload
    if (raw.payload && typeof raw.payload === 'object' && !Array.isArray(raw.payload)) {
      base = Object.assign({}, raw.payload, raw);
      try { delete base.payload; } catch (e) { base.payload = undefined; }
    }
    var job = Object.assign({}, base);
    if ((job.type == null || job.type === '') && job.type_slug) job.type = job.type_slug;
    if ((job.type_name == null || job.type_name === '') && job.type_label) job.type_name = job.type_label;
    if (job.rush_fee_enabled == null && job.rush_fee != null) job.rush_fee_enabled = job.rush_fee;
    if (job.budget_max == null && job.budget != null) job.budget_max = job.budget;
    if (job.budget_min == null && job.budget_from != null) job.budget_min = job.budget_from;
    if ((job.closes_at == null || job.closes_at === '') && job.deadline) job.closes_at = job.deadline;
    if (!job.audience_label && job.audience != null && job.audience !== '') {
      job.audience_label = audienceLabel(job.audience);
    } else if (job.audience_label) {
      job.audience_label = audienceLabel(job.audience_label);
    }
    if ((job.contact_hours == null || job.contact_hours === '') && (job.contact_hours_from || job.contact_hours_to)) {
      job.contact_hours = String(job.contact_hours_from || '') + ' ~ ' + String(job.contact_hours_to || '');
    }
    if ((!job.provided_extensions || !job.provided_extensions.length)) {
      var built = [];
      var seenExt = {};
      Object.keys(job).forEach(function (k) {
        if (k.indexOf('ext_') !== 0) return;
        if (!(job[k] === true || job[k] === 1 || job[k] === '1')) return;
        var token = String(k.slice(4) || '').toUpperCase();
        if (!token || seenExt[token]) return;
        seenExt[token] = 1;
        built.push(token);
      });
      if (built.length) job.provided_extensions = built;
    }
    if (job.provided_extensions != null) {
      job.provided_extensions = (function normalizeExtListPage(raw) {
        var out = [];
        var seen = {};
        var push = function (tok) {
          if (tok == null || tok === '') return;
          var s = '';
          if (typeof tok === 'object') {
            var v = tok.value != null ? tok.value : tok.label != null ? tok.label : tok.ext != null ? tok.ext : tok.slug != null ? tok.slug : '';
            if (typeof v === 'object') return;
            s = String(v == null ? '' : v);
          } else {
            s = String(tok);
          }
          s = s.replace(/^\./, '').trim().toUpperCase();
          if (!s || s.indexOf('[OBJECT') === 0 || s === 'OBJECT]' || !/^[A-Z0-9]{1,16}$/.test(s)) return;
          if (seen[s]) return;
          seen[s] = 1;
          out.push(s);
        };
        if (Array.isArray(raw)) raw.forEach(push);
        else if (typeof raw === 'string') String(raw).split(/[\s,;|]+/).forEach(push);
        else if (typeof raw === 'object') Object.keys(raw).forEach(function (k) { push(raw[k]); });
        return out;
      })(job.provided_extensions);
    }
    return job;
  }

  function looksLikeJob(value) {
    if (!value || typeof value !== 'object' || Array.isArray(value)) return false;
    // Reject empty create/edit form shells (status/audience defaults, no id)
    if (value.id == null && !value.type_name && !value.budget_label && !value.privacy_visible
      && !(value.title && String(value.title).trim()) && !(value.description && String(value.description).trim())) {
      return false;
    }
    return !!(value.id || value.title || value.type || value.type_name || value.type_slug
      || value.budget_label || value.budget_min != null || value.closes_at || value.status_label);
  }

  function matchRouteJob(value) {
    var id = jobIdFromLocation();
    var job = normalizeJob(value);
    if (!looksLikeJob(job)) return null;
    // On detail pages, never accept id-less form state (was painting every field 해당없음)
    if (id) {
      if (job.id == null || String(job.id) !== id) return null;
    }
    return job;
  }

  function pickRicher(a, b) {
    if (!a) return b;
    if (!b) return a;
    return jobRichness(b) > jobRichness(a) ? b : a;
  }

  function fromState(keys) {
    var i;
    var value;
    var best = null;
    for (i = 0; i < keys.length; i++) {
      value = matchRouteJob(unwrap(getState(keys[i])));
      best = pickRicher(best, value);
    }
    return best;
  }

  function walkStateForJob(node, depth, seen) {
    if (!node || typeof node !== 'object' || depth > 8) return null;
    if (seen.has(node)) return null;
    seen.add(node);
    var matched = matchRouteJob(unwrap(node));
    if (matched) return matched;
    var key;
    var child;
    if (Array.isArray(node)) {
      for (key = 0; key < Math.min(node.length, 40); key++) {
        child = walkStateForJob(node[key], depth + 1, seen);
        if (child) return child;
      }
      return null;
    }
    for (key in node) {
      if (!Object.prototype.hasOwnProperty.call(node, key)) continue;
      if (key === '_global' || key === 'window' || key === 'document') continue;
      child = walkStateForJob(node[key], depth + 1, seen);
      if (child) return child;
    }
    return null;
  }

  function jobFromFullState() {
    try {
      if (!(window.G7Core && window.G7Core.state && typeof window.G7Core.state.get === 'function')) return null;
      var root = window.G7Core.state.get();
      return walkStateForJob(root, 0, new Set());
    } catch (e) {
      return null;
    }
  }

  function resolveJob() {
    var fromKeys = fromState(JOB_KEYS);
    var walked = jobFromFullState();
    var best = pickRicher(fromKeys, walked);
    best = pickRicher(best, cachedJob && matchRouteJob(cachedJob));
    return best || null;
  }

  function resolveViewer() {
    var i;
    var value;
    for (i = 0; i < VIEWER_KEYS.length; i++) {
      value = unwrap(getState(VIEWER_KEYS[i]));
      if (value && typeof value === 'object' && !Array.isArray(value)
        && (value.can_view_privacy != null || value.is_owner != null || value.authenticated != null || value.privacy)) {
        return value;
      }
    }
    return unwrap(getState('viewer.data')) || unwrap(getState('viewer')) || cachedViewer || {};
  }

  function isEmpty(value) {
    if (value === null || value === undefined) return true;
    if (Array.isArray(value)) return value.length === 0;
    return typeof value === 'string' && value.trim() === '';
  }

  function valueOrNone(value) {
    if (isEmpty(value)) return NONE;
    if (Array.isArray(value)) return value.join(', ') || NONE;
    return String(value);
  }

  function formatNumber(value) {
    if (value === null || value === undefined || value === '') return '';
    if (typeof value === 'string' && value.indexOf(',') >= 0) return value;
    var number = Number(String(value).replace(/[^0-9.-]/g, ''));
    if (isNaN(number)) return String(value);
    return Math.round(number).toLocaleString('ko-KR');
  }

  function money(value) {
    if (isEmpty(value)) return NONE;
    var formatted = formatNumber(value);
    if (!formatted) return NONE;
    return /원$/.test(formatted) ? formatted : formatted + '원';
  }

  window.CMB = window.CMB || {};
  window.CMB.formatNumber = formatNumber;
  window.CMB.formatMoney = money;

  function count(value) {
    if (isEmpty(value)) return NONE;
    return String(value) + '회';
  }

  function booleanLabel(value, on, off) {
    if (isEmpty(value)) return NONE;
    var enabled = value === true || value === 1 || value === '1' || value === 'true';
    return enabled ? on : off;
  }

  function fileNames(value) {
    if (!Array.isArray(value) || !value.length) return NONE;
    var names = value.map(function (file) {
      return file && (file.original_filename || file.name || file.filename);
    }).filter(function (name) { return !isEmpty(name); });
    return names.length ? names.join(', ') : NONE;
  }

  function sizeValue(job) {
    if (!isEmpty(job.size_label)) return job.size_label;
    if (Array.isArray(job.sizes) && job.sizes.length) {
      return job.sizes.map(function (row) {
        if (!row || typeof row !== 'object') return '';
        var dims = [row.w, row.d, row.h].map(function (v) { return isEmpty(v) ? '-' : v; }).join(' × ');
        return (isEmpty(row.name) ? '' : row.name + ' ') + dims + ' mm';
      }).filter(Boolean).join(', ');
    }
    return NONE;
  }

  function includesModeling(job) {
    return job.type_includes_modeling !== false;
  }

  function requiresAddress(job) {
    return job.type_requires_address !== false;
  }

  var CATALOG = [
    { section: '의뢰 정보' },
    { label: '제목', key: 'title', wide: true },
    { label: '유형', value: function (job) { return job.type_name || job.type_label || job.type; } },
    { label: '예산', value: function (job) { return job.budget_label || null; }, format: function (v) { return isEmpty(v) ? NONE : String(v); } },
    { label: '예산 최소', key: 'budget_min', format: money },
    { label: '예산 최대', value: function (job) { return job.budget_max == null ? job.budget : job.budget_max; }, format: money },
    { label: '마감 시각', value: function (job) { return job.closes_at_local || job.closes_at || job.deadline; } },
    { label: '급행비 가능', key: 'rush_fee_enabled', format: function (v) { return booleanLabel(v, '가능', '불가'); } },
    { label: '급행 적용 조건', value: function (job) { return job.rush_deadline_label || job.rush_deadline; } },
    { label: '스케줄 선점비 가능', key: 'schedule_premium_enabled', format: function (v) { return booleanLabel(v, '가능', '불가'); } },
    { label: '상태', value: function (job) { return job.status_label || job.status; } },
    { label: '입찰 권한', value: function (job) {
      if (job.audience_label) return audienceLabel(job.audience_label);
      if (job.audience != null && job.audience !== '') return audienceLabel(job.audience);
      return null;
    } },
    { section: '제작 사양' },
    { label: '최종 크기', value: sizeValue, wide: true },
    { label: '제공 확장자', value: function (job) {
      var ext = job.provided_extensions;
      if (Array.isArray(ext) && ext.length) return ext;
      var flags = [];
      [['ext_stl', 'STL'], ['ext_3mf', '3MF'], ['ext_obj', 'OBJ'], ['ext_step', 'STEP'],
        ['ext_stp', 'STP'], ['ext_gcode', 'GCODE'], ['ext_fbx', 'FBX'], ['ext_dwg', 'DWG']].forEach(function (pair) {
        if (job[pair[0]] === true || job[pair[0]] === 1 || job[pair[0]] === '1') flags.push(pair[1]);
      });
      return flags.length ? flags : null;
    }, applies: includesModeling, wide: true, format: function (v) {
      if (isEmpty(v)) return NONE;
      return Array.isArray(v) ? v.join(', ') : String(v);
    } },
    { label: '소유권한', key: 'ownership_requested', format: function (v) { return booleanLabel(v, '요청함', '요청 안 함'); } },
    { label: '수정 횟수 적용', key: 'revision_enabled', format: function (v) { return booleanLabel(v, '적용', '미적용'); } },
    { label: '최소 수정 횟수', key: 'revision_count', format: count },
    { label: '회당 / 최대 수정비용', key: 'revision_cost', format: money },
    { label: '설명', key: 'description', wide: true, multiline: true },
    { label: '압축 파일', key: 'archives', format: fileNames, private: true, wide: true },
    { section: '개인정보', privacyCard: true },
    { label: '주문자명 또는 업체명', key: 'contact_name', private: true },
    { label: '연락처', key: 'contact_phone', private: true },
    { label: '연락 가능시간', value: function (job, privacy) {
      var h = privateValue(job, privacy, 'contact_hours');
      if (!isEmpty(h)) return h;
      var from = privateValue(job, privacy, 'contact_hours_from') || job.contact_hours_from;
      var to = privateValue(job, privacy, 'contact_hours_to') || job.contact_hours_to;
      if (!isEmpty(from) || !isEmpty(to)) return String(from || '') + ' ~ ' + String(to || '');
      return null;
    }, private: true },
    { label: '이메일', key: 'contact_email', private: true },
    { label: '우편번호', key: 'zipcode', private: true, applies: requiresAddress },
    { label: '주소', key: 'address', private: true, applies: requiresAddress, wide: true },
    { label: '상세 주소', key: 'address_detail', private: true, applies: requiresAddress, wide: true },
    { label: '담당자 명', key: 'manager_name', private: true },
    { label: '담당자 연락처', key: 'manager_phone', private: true },
    { label: '담당자 이메일', key: 'manager_email', private: true }
  ];
  window.CMB_JOB_SPEC_CATALOG = CATALOG;

  var FALLBACK_KEYS = [
    ['title', '제목'],
    ['type_name', '유형'],
    ['type', '유형 코드'],
    ['budget_label', '예산'],
    ['budget_min', '예산 최소'],
    ['budget_max', '예산 최대'],
    ['closes_at_local', '마감 시각'],
    ['closes_at', '마감 시각'],
    ['rush_fee_enabled', '급행비 가능'],
    ['rush_deadline_label', '급행 적용 조건'],
    ['schedule_premium_enabled', '스케줄 선점비 가능'],
    ['status_label', '상태'],
    ['status', '상태'],
    ['audience_label', '입찰 권한'],
    ['audience', '입찰 권한'],
    ['size_label', '최종 크기'],
    ['provided_extensions', '제공 확장자'],
    ['ownership_requested', '소유권한'],
    ['revision_enabled', '수정 횟수 적용'],
    ['revision_count', '최소 수정 횟수'],
    ['revision_cost', '회당 / 최대 수정비용'],
    ['description', '설명'],
    ['contact_name', '주문자명 또는 업체명'],
    ['contact_phone', '연락처'],
    ['contact_hours', '연락 가능시간'],
    ['contact_email', '이메일']
  ];

  function privateValue(job, privacy, key) {
    if (!isEmpty(job[key])) return job[key];
    return privacy && !isEmpty(privacy[key]) ? privacy[key] : null;
  }

  function rawValue(field, job, privacy) {
    if (typeof field.value === 'function') return field.value(job, privacy);
    return field.private ? privateValue(job, privacy, field.key) : job[field.key];
  }

  function addSection(host, label) {
    var heading = document.createElement('h3');
    heading.className = 'cmb-job-card-title sm:col-span-2';
    heading.textContent = label;
    host.appendChild(heading);
  }

  function addField(host, field, value) {
    var row = document.createElement('p');
    row.className = (field.multiline ? 'cmb-show-desc whitespace-pre-wrap' : 'cmb-page-guide') + (field.wide ? ' sm:col-span-2' : '');
    var label = document.createElement('strong');
    label.className = 'font-medium text-gray-900 dark:text-white';
    label.textContent = field.label + ': ';
    var text = document.createElement('span');
    text.textContent = value;
    row.appendChild(label);
    row.appendChild(text);
    host.appendChild(row);
  }

  var lightboxImages = [];
  var lightboxIndex = 0;
  var lightboxPreviousOverflow = '';
  var cachedJob = null;
  var cachedViewer = null;
  var fetchInFlight = null;
  var fetchAttemptedFor = '';
  var rendering = false;
  var pollTimer = null;

  function imageUrl(file) {
    if (!file || typeof file !== 'object') return '';
    return String(file.download_url || file.url || file.src || '');
  }

  function isImageFile(file) {
    if (!file || typeof file !== 'object') return false;
    if (file.is_image === true || file.is_image === 1 || file.is_image === '1') return true;
    var mime = String(file.mime_type || file.mime || file.type || '').toLowerCase();
    if (mime.indexOf('image/') === 0) return true;
    var name = String(file.original_filename || file.filename || file.name || imageUrl(file)).split('?')[0].toLowerCase();
    return /\.(avif|bmp|gif|jpe?g|png|svg|webp)$/.test(name);
  }

  function jobImages(job) {
    var groups = [job.images, job.files, job.attachments, job.archives, job.deliveries];
    var seen = {};
    var out = [];
    groups.forEach(function (group) {
      if (!Array.isArray(group)) return;
      group.forEach(function (file) {
        var url = imageUrl(file);
        if (!url || !isImageFile(file) || seen[url]) return;
        seen[url] = true;
        out.push({
          url: url,
          alt: String(file.original_filename || file.filename || file.name || '의뢰 이미지')
        });
      });
    });
    return out;
  }

  function ensureLightbox() {
    var modal = document.getElementById('cmb-job-lightbox');
    if (modal) return modal;
    modal = document.createElement('div');
    modal.id = 'cmb-job-lightbox';
    modal.className = 'cmb-job-lightbox';
    modal.hidden = true;
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-label', '의뢰 이미지 보기');
    modal.innerHTML =
      '<button type="button" class="cmb-job-lightbox-close" aria-label="닫기">×</button>' +
      '<button type="button" class="cmb-job-lightbox-nav is-prev" aria-label="이전 이미지">‹</button>' +
      '<div class="cmb-job-lightbox-viewport"><div class="cmb-job-lightbox-track"></div></div>' +
      '<button type="button" class="cmb-job-lightbox-nav is-next" aria-label="다음 이미지">›</button>' +
      '<p class="cmb-job-lightbox-count" aria-live="polite"></p>';
    document.body.appendChild(modal);
    modal.querySelector('.cmb-job-lightbox-close').addEventListener('click', closeLightbox);
    modal.querySelector('.is-prev').addEventListener('click', function () { moveLightbox(-1); });
    modal.querySelector('.is-next').addEventListener('click', function () { moveLightbox(1); });
    modal.addEventListener('click', function (event) {
      if (event.target === modal) closeLightbox();
    });
    return modal;
  }

  function updateLightbox() {
    var modal = document.getElementById('cmb-job-lightbox');
    if (!modal || modal.hidden) return;
    modal.querySelector('.cmb-job-lightbox-track').style.transform = 'translate3d(-' + (lightboxIndex * 100) + '%, 0, 0)';
    modal.querySelector('.cmb-job-lightbox-count').textContent = (lightboxIndex + 1) + ' / ' + lightboxImages.length;
    modal.querySelector('.is-prev').hidden = lightboxImages.length < 2;
    modal.querySelector('.is-next').hidden = lightboxImages.length < 2;
  }

  function moveLightbox(delta) {
    if (lightboxImages.length < 2) return;
    lightboxIndex = (lightboxIndex + delta + lightboxImages.length) % lightboxImages.length;
    updateLightbox();
  }

  function openLightbox(images, index) {
    lightboxImages = images.slice();
    lightboxIndex = Math.max(0, Math.min(Number(index) || 0, lightboxImages.length - 1));
    var modal = ensureLightbox();
    var track = modal.querySelector('.cmb-job-lightbox-track');
    track.innerHTML = '';
    lightboxImages.forEach(function (image, idx) {
      var slide = document.createElement('div');
      slide.className = 'cmb-job-lightbox-slide';
      var img = document.createElement('img');
      img.src = image.url;
      img.alt = image.alt;
      img.loading = idx === lightboxIndex ? 'eager' : 'lazy';
      slide.appendChild(img);
      track.appendChild(slide);
    });
    modal.hidden = false;
    modal.classList.add('is-open');
    lightboxPreviousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    updateLightbox();
    try { modal.querySelector('.cmb-job-lightbox-close').focus(); } catch (e) {}
  }

  function closeLightbox() {
    var modal = document.getElementById('cmb-job-lightbox');
    if (!modal || modal.hidden) return;
    modal.classList.remove('is-open');
    modal.hidden = true;
    document.body.style.overflow = lightboxPreviousOverflow;
  }

  function renderGallery(host, job) {
    if (!host) return;
    var body = host.closest ? host.closest('.cmb-job-spec-body') : null;
    var images = jobImages(job);
    host.innerHTML = '';
    if (!images.length) {
      host.hidden = true;
      if (body) body.classList.remove('has-gallery');
      return;
    }
    host.hidden = false;
    if (body) body.classList.add('has-gallery');
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'cmb-job-spec-gallery-button';
    button.setAttribute('aria-label', '의뢰 이미지 ' + images.length + '장 보기');
    var image = document.createElement('img');
    image.src = images[0].url;
    image.alt = images[0].alt;
    image.loading = 'lazy';
    button.appendChild(image);
    if (images.length > 1) {
      var badge = document.createElement('span');
      badge.className = 'cmb-job-spec-gallery-count';
      badge.textContent = '+' + (images.length - 1);
      button.appendChild(badge);
    }
    button.addEventListener('click', function () { openLightbox(images, 0); });
    host.appendChild(button);
  }

  if (!window.__cmbJobLightboxKeys) {
    window.__cmbJobLightboxKeys = true;
    document.addEventListener('keydown', function (event) {
      var modal = document.getElementById('cmb-job-lightbox');
      if (!modal || modal.hidden) return;
      if (event.key === 'Escape') closeLightbox();
      if (event.key === 'ArrowLeft') moveLightbox(-1);
      if (event.key === 'ArrowRight') moveLightbox(1);
    });
  }

  function signatureOf(value) {
    var text;
    var hash = 5381;
    var i;
    try { text = JSON.stringify(value); } catch (e) { text = String(value); }
    for (i = 0; i < text.length; i++) hash = ((hash << 5) + hash) ^ text.charCodeAt(i);
    return String(hash >>> 0);
  }

  function renderFallback(host, job) {
    addSection(host, '의뢰 명세');
    FALLBACK_KEYS.forEach(function (pair) {
      var key = pair[0];
      var label = pair[1];
      if (!(key in job) && key !== 'audience_label') return;
      var raw = job[key];
      if (key === 'audience' || key === 'audience_label') {
        raw = job.audience_label || audienceLabel(job.audience);
      }
      var value;
      if (key.indexOf('budget') >= 0 && key !== 'budget_label') value = money(raw);
      else if (key === 'rush_fee_enabled' || key === 'schedule_premium_enabled' || key === 'ownership_requested' || key === 'revision_enabled') {
        value = booleanLabel(raw, key === 'ownership_requested' ? '요청함' : (key.indexOf('revision') === 0 ? '적용' : '가능'),
          key === 'ownership_requested' ? '요청 안 함' : (key.indexOf('revision') === 0 ? '미적용' : '불가'));
      } else if (key === 'revision_count') value = count(raw);
      else if (key === 'revision_cost') value = money(raw);
      else if (key === 'provided_extensions') value = Array.isArray(raw) ? (raw.length ? raw.join(', ') : NONE) : valueOrNone(raw);
      else if (key === 'audience' || key === 'audience_label') value = valueOrNone(raw);
      else value = valueOrNone(raw);
      addField(host, { label: label, wide: key === 'description', multiline: key === 'description' }, value);
    });
  }

  function renderCatalog(host, job, viewer, canViewPrivate, privacy, privacyOnly) {
    var inPrivacy = false;
    CATALOG.forEach(function (field) {
      if (field.section) {
        inPrivacy = !!field.privacyCard;
        if (privacyOnly) {
          if (inPrivacy) addSection(host, field.section);
          return;
        }
        if (inPrivacy) return; // skip privacy heading in main spec
        addSection(host, field.section);
        return;
      }
      if (privacyOnly ? !inPrivacy : inPrivacy) return;
      if (field.applies && !field.applies(job)) return;
      var value = field.private && !canViewPrivate ? PRIVATE : rawValue(field, job, privacy);
      if (!(field.private && !canViewPrivate)) {
        value = field.format ? field.format(value) : valueOrNone(value);
      }
      addField(host, field, value);
    });
  }

  function renderPrivacy(host, job, viewer, canViewPrivate, privacy) {
    if (!host) return;
    host.innerHTML = '';
    try {
      renderCatalog(host, job, viewer, canViewPrivate, privacy, true);
    } catch (err) {
      host.innerHTML = '';
    }
    if (!canViewPrivate) {
      var note = document.createElement('p');
      note.className = 'cmb-hint sm:col-span-2';
      note.textContent = '연락처·배송지 등 개인정보는 의뢰 본인, 관리자, 낙찰 완료 후에만 확인할 수 있습니다.';
      host.appendChild(note);
    }
    host.setAttribute('data-cmb-job-privacy-ready', '1');
  }

  function render(host, job, viewer) {
    var admin = /\/admin\/maker-bids\/jobs\/\d+/.test(String(location.pathname || ''));
    var canViewPrivate = admin
      || job.privacy_visible === true
      || !!(viewer && viewer.can_view_privacy)
      || !!(viewer && viewer.is_owner);
    var privacy = viewer && viewer.privacy && typeof viewer.privacy === 'object' ? viewer.privacy : {};
    var signature = signatureOf([job.id, job.updated_at, canViewPrivate, job.budget_max, job.description, privacy]);
    if (host.getAttribute('data-cmb-job-spec-signature') === signature) return;
    rendering = true;
    try {
      if (window.__cmbJobSpecObs) {
        try { window.__cmbJobSpecObs.disconnect(); } catch (e) {}
      }
      host.innerHTML = '';
      try {
        renderCatalog(host, job, viewer, canViewPrivate, privacy, false);
      } catch (err) {
        host.innerHTML = '';
        renderFallback(host, job);
      }
      var privacyHost = document.querySelector('[data-cmb-job-privacy]');
      try { renderPrivacy(privacyHost, job, viewer, canViewPrivate, privacy); } catch (e) {}
      var body = host.closest ? host.closest('.cmb-job-spec-body') : null;
      try { renderGallery(body && body.querySelector('[data-cmb-job-gallery]'), job); } catch (e) {}
      host.setAttribute('data-cmb-job-spec-signature', signature);
      host.setAttribute('data-cmb-job-spec-ready', '1');
    } finally {
      rendering = false;
      if (window.__cmbJobSpecObs) {
        try {
          window.__cmbJobSpecObs.observe(document.documentElement, {
            childList: true,
            subtree: true,
            characterData: true,
            attributes: true
          });
        } catch (e) {}
      }
    }
  }

  function pendingHosts() {
    var hosts = document.querySelectorAll('[data-cmb-job-spec]');
    var pending = [];
    hosts.forEach(function (host) {
      if (!host.getAttribute('data-cmb-job-spec-ready')) pending.push(host);
    });
    return pending;
  }

  function jobFetchUrl(id) {
    var admin = /\/admin\/maker-bids\/jobs\//.test(String(location.pathname || ''));
    if (admin) return '/api/modules/custom-maker_bids/admin/jobs/' + id;
    return '/api/modules/custom-maker_bids/jobs/' + id;
  }

  function ingestFetched(payload) {
    var job = matchRouteJob(unwrap(payload));
    if (!job) return null;
    cachedJob = job;
    return job;
  }

  function fetchJobIfNeeded() {
    var id = jobIdFromLocation();
    if (!id || !pendingHosts().length) return;
    if (fetchAttemptedFor === id && fetchInFlight) return;
    if (cachedJob && String(cachedJob.id) === id) {
      scan(true);
      return;
    }
    fetchAttemptedFor = id;
    var url = jobFetchUrl(id);
    var handle = function (payload) {
      fetchInFlight = null;
      if (ingestFetched(payload)) scan(true);
    };
    var fail = function () { fetchInFlight = null; };
    if (window.G7Core && window.G7Core.api && typeof window.G7Core.api.get === 'function') {
      fetchInFlight = window.G7Core.api.get(url).then(handle).catch(fail);
      return;
    }
    fetchInFlight = fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } })
      .then(function (res) { return res.ok ? res.json() : Promise.reject(); })
      .then(handle)
      .catch(fail);
  }

  function scan(fromFetch) {
    if (rendering) return;
    var hosts = document.querySelectorAll('[data-cmb-job-spec]');
    if (!hosts.length) return;
    var job = resolveJob();
    if (!job) {
      if (!fromFetch) fetchJobIfNeeded();
      return;
    }
    cachedJob = job;
    var viewer = resolveViewer();
    cachedViewer = viewer;
    hosts.forEach(function (host) {
      try { render(host, job, viewer); } catch (e) {
        try {
          host.innerHTML = '';
          renderFallback(host, job);
          host.setAttribute('data-cmb-job-spec-ready', '1');
        } catch (e2) {}
      }
    });
  }

  function ensurePolling() {
    if (pollTimer) return;
    var ticks = 0;
    pollTimer = setInterval(function () {
      ticks += 1;
      if (!pendingHosts().length) {
        if (ticks > 40) {
          clearInterval(pollTimer);
          pollTimer = null;
        }
        return;
      }
      scan();
      if (ticks === 3 || ticks === 8 || ticks === 15) fetchJobIfNeeded();
      if (ticks > 120) {
        clearInterval(pollTimer);
        pollTimer = null;
      }
    }, 500);
  }

  function boot() {
    scan();
    ensurePolling();
  }

  boot();
  document.addEventListener('DOMContentLoaded', boot);
  setTimeout(boot, 100);
  setTimeout(boot, 400);
  setTimeout(boot, 1000);
  setTimeout(boot, 2500);
  setTimeout(function () { fetchJobIfNeeded(); }, 600);

  if (!window.__cmbJobSpecObs) {
    window.__cmbJobSpecObs = new MutationObserver(function () {
      if (rendering) return;
      ensurePolling();
      scan();
    });
    try {
      window.__cmbJobSpecObs.observe(document.documentElement, {
        childList: true,
        subtree: true,
        characterData: true,
        attributes: true
      });
    } catch (e) {}
  }

  if (!window.__cmbJobSpecSub && window.G7Core && window.G7Core.state && typeof window.G7Core.state.subscribe === 'function') {
    window.__cmbJobSpecSub = true;
    try {
      window.G7Core.state.subscribe(function () {
        ensurePolling();
        scan();
      });
    } catch (e) {}
  }

  if (!window.__cmbJobSpecNav) {
    window.__cmbJobSpecNav = true;
    window.addEventListener('popstate', function () {
      fetchAttemptedFor = '';
      cachedJob = null;
      ensurePolling();
      boot();
    });
  }
})();

/* cmb-pager */
(function () {
  function qs(name) {
    try { return new URLSearchParams(location.search).get(name); } catch (e) { return null; }
  }
  function setParam(param, value) {
    var u;
    try { u = new URL(location.href); } catch (e) { return; }
    if (value == null || value === '' || Number(value) <= 1 && param.indexOf('page') >= 0 && String(value) === '1') {
      // keep page=1 explicit for clarity on first page navigation from page 2+
    }
    u.searchParams.set(param, String(value));
    if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
      window.G7Core.dispatch({ handler: 'navigate', params: { path: u.pathname + u.search } });
      return;
    }
    location.href = u.pathname + u.search;
  }
  function pagesAround(cur, last) {
    var out = [];
    var start = Math.max(1, cur - 2);
    var end = Math.min(last, cur + 2);
    if (start > 1) out.push(1);
    if (start > 2) out.push('…');
    var i;
    for (i = start; i <= end; i++) out.push(i);
    if (end < last - 1) out.push('…');
    if (end < last) out.push(last);
    return out;
  }
  function render(el) {
    var page = parseInt(el.getAttribute('data-page') || '1', 10) || 1;
    var total = parseInt(el.getAttribute('data-total') || '0', 10) || 0;
    var per = parseInt(el.getAttribute('data-per-page') || '10', 10) || 10;
    var last = parseInt(el.getAttribute('data-last-page') || '0', 10) || Math.max(1, Math.ceil(total / per));
    var param = el.getAttribute('data-param') || 'page';
    if (total <= per && last <= 1) {
      el.classList.add('is-empty');
      el.hidden = true;
      el.innerHTML = '';
      return;
    }
    el.hidden = false;
    el.classList.remove('is-empty');
    el.innerHTML = '';
    function btn(label, target, opts) {
      opts = opts || {};
      var a = document.createElement(opts.disabled ? 'span' : 'button');
      a.className = 'cmb-pager-btn' + (opts.active ? ' is-active' : '') + (opts.disabled ? ' is-disabled' : '');
      a.textContent = label;
      if (!opts.disabled && !opts.ellipsis) {
        a.type = 'button';
        a.addEventListener('click', function (e) {
          e.preventDefault();
          setParam(param, target);
        });
      }
      el.appendChild(a);
    }
    btn('이전', page - 1, { disabled: page <= 1 });
    pagesAround(page, last).forEach(function (n) {
      if (n === '…') {
        var s = document.createElement('span');
        s.className = 'cmb-pager-meta';
        s.textContent = '…';
        el.appendChild(s);
        return;
      }
      btn(String(n), n, { active: n === page });
    });
    btn('다음', page + 1, { disabled: page >= last });
    var meta = document.createElement('span');
    meta.className = 'cmb-pager-meta';
    meta.textContent = page + ' / ' + last + ' · ' + total + '건';
    el.appendChild(meta);
  }
  function scan() {
    document.querySelectorAll('[data-cmb-pager]').forEach(render);
  }
  scan();
  document.addEventListener('DOMContentLoaded', scan);
  setTimeout(scan, 200);
  setTimeout(scan, 800);
  setTimeout(scan, 1600);
  if (!window.__cmbPagerObs) {
    window.__cmbPagerObs = new MutationObserver(function () { scan(); });
    try { window.__cmbPagerObs.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-page', 'data-total', 'data-last-page'] }); } catch (e) {}
  }
})();


/* cmb-list-search: soft search without full page refresh */
(function () {
  function dispatch(handler, params) {
    if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
      window.G7Core.dispatch({ handler: handler, params: params || {} });
      return true;
    }
    return false;
  }

  function searchInput() {
    return document.querySelector('[data-cmb-search-input], .cmb-search-input, .cmb-search-bar input[name="q"]');
  }

  function currentQ() {
    var el = searchInput();
    if (el && el.value != null) {
      return String(el.value).trim();
    }
    try {
      return String(new URLSearchParams(location.search).get('q') || '').trim();
    } catch (e) {
      return '';
    }
  }

  function softPath(q) {
    var u;
    try { u = new URL(location.href); } catch (e) { return null; }
    if (q) {
      u.searchParams.set('q', q);
    } else {
      u.searchParams.delete('q');
    }
    u.searchParams.delete('page');
    // preserve sort
    return u.pathname + (u.search || '');
  }

  function runSearch(e) {
    if (e) {
      try { e.preventDefault(); } catch (err) {}
      try { e.stopPropagation(); } catch (err2) {}
      try { e.stopImmediatePropagation && e.stopImmediatePropagation(); } catch (err3) {}
    }
    if (!document.querySelector('[data-cmb-search-bar], .cmb-search-bar')) {
      return false;
    }
    var q = currentQ();
    dispatch('setState', { target: 'local', 'search.q': q, 'search.page': 1 });
    var path = softPath(q);
    if (path) {
      try {
        if (window.history && typeof window.history.replaceState === 'function') {
          window.history.replaceState({}, '', path);
        }
      } catch (err4) {}
      // Soft SPA navigate when available (same pattern as pager) — still avoid hard reload.
      if (window.G7Core && typeof window.G7Core.navigate === 'function') {
        try { window.G7Core.navigate(path); } catch (err5) {}
      } else {
        dispatch('navigate', { path: path });
      }
    }
    dispatch('refetchDataSource', { dataSourceId: 'jobs' });
    return false;
  }

  function syncInputFromUrl() {
    var el = searchInput();
    if (!el) return;
    var q = '';
    try { q = String(new URLSearchParams(location.search).get('q') || ''); } catch (e) {}
    if (q && !String(el.value || '').trim()) {
      el.value = q;
      dispatch('setState', { target: 'local', 'search.q': q });
    }
  }

  function bind() {
    syncInputFromUrl();
    var bar = document.querySelector('[data-cmb-search-bar], .cmb-search-bar');
    if (!bar || bar.getAttribute('data-cmb-search-bound')) {
      return;
    }
    bar.setAttribute('data-cmb-search-bound', '1');
    bar.addEventListener('click', function (e) {
      var t = e.target;
      if (!t) return;
      var go = t.closest ? t.closest('[data-cmb-search-go], .cmb-search-go, button') : null;
      if (!go || !bar.contains(go)) return;
      if (go.matches && !go.matches('[data-cmb-search-go], .cmb-search-go') && go.tagName !== 'BUTTON') return;
      if (go.classList.contains('cmb-search-go') || go.getAttribute('data-cmb-search-go') || (go.textContent || '').indexOf('검색') >= 0) {
        runSearch(e);
      }
    }, true);
    bar.addEventListener('keydown', function (e) {
      if (!e || e.key !== 'Enter') return;
      var t = e.target;
      if (!t) return;
      if (t.matches && (t.matches('[data-cmb-search-input], .cmb-search-input, input[name="q"]') || t.tagName === 'INPUT')) {
        runSearch(e);
      }
    }, true);
    var form = bar.closest('form');
    if (form && !form.getAttribute('data-cmb-search-submit-bound')) {
      form.setAttribute('data-cmb-search-submit-bound', '1');
      form.addEventListener('submit', function (e) {
        if (bar.contains(e.target) || form.contains(searchInput())) {
          runSearch(e);
        }
      }, true);
    }
  }

  bind();
  document.addEventListener('DOMContentLoaded', bind);
  setTimeout(bind, 200);
  setTimeout(bind, 800);
  setTimeout(bind, 1600);
  if (!window.__cmbSearchObs) {
    window.__cmbSearchObs = new MutationObserver(function () { bind(); });
    try { window.__cmbSearchObs.observe(document.documentElement, { childList: true, subtree: true }); } catch (e) {}
  }
})();


/* cmb-list-sort: soft sort without full page refresh */
(function () {
  function dispatch(handler, params) {
    if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
      window.G7Core.dispatch({ handler: handler, params: params || {} });
      return true;
    }
    return false;
  }
  function softPath(updates) {
    var u;
    try { u = new URL(location.href); } catch (e) { return null; }
    Object.keys(updates || {}).forEach(function (k) {
      var v = updates[k];
      if (v == null || v === '') u.searchParams.delete(k);
      else u.searchParams.set(k, String(v));
    });
    return u.pathname + (u.search || '');
  }
  function applySort(sort) {
    sort = String(sort || 'latest');
    dispatch('setState', { target: 'local', 'search.sort': sort, 'search.page': 1 });
    var path = softPath({ sort: sort === 'latest' ? null : sort, page: null });
    if (path) {
      try { window.history.replaceState({}, '', path); } catch (e) {}
      if (window.G7Core && typeof window.G7Core.navigate === 'function') {
        try { window.G7Core.navigate(path); } catch (e2) {}
      } else {
        dispatch('navigate', { path: path });
      }
    }
    dispatch('refetchDataSource', { dataSourceId: 'jobs' });
  }
  function bind() {
    var sel = document.querySelector('[data-cmb-sort-select], .cmb-search-sort-select, select[name="sort"]');
    if (!sel || sel.getAttribute('data-cmb-sort-bound')) return;
    sel.setAttribute('data-cmb-sort-bound', '1');
    // sync from URL
    try {
      var cur = new URLSearchParams(location.search).get('sort');
      if (cur && sel.value !== cur) sel.value = cur;
    } catch (e) {}
    sel.addEventListener('change', function (e) {
      try { e.preventDefault(); e.stopPropagation(); } catch (err) {}
      applySort(sel.value || 'latest');
    }, true);
  }
  bind();
  document.addEventListener('DOMContentLoaded', bind);
  setTimeout(bind, 300);
  setTimeout(bind, 1200);
  if (!window.__cmbSortObs) {
    window.__cmbSortObs = new MutationObserver(function () { bind(); });
    try { window.__cmbSortObs.observe(document.documentElement, { childList: true, subtree: true }); } catch (e) {}
  }
})();

/* cmb-open-bid-form: inline 견적 넣기 on 입찰현황 */
(function () {
  function toast(type, message) {
    if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
      window.G7Core.dispatch({ handler: 'toast', params: { type: type, message: message } });
    }
  }
  function dispatch(handler, params) {
    if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
      window.G7Core.dispatch({ handler: handler, params: params || {} });
    }
  }
  function csrf() {
    var m = document.querySelector('meta[name="csrf-token"]');
    if (m && m.content) return m.content;
    try {
      var match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
      if (match) return decodeURIComponent(match[1]);
    } catch (e) {}
    return '';
  }
  function showPanel(panel, show) {
    if (!panel) return;
    if (show) {
      panel.hidden = false;
      panel.removeAttribute('hidden');
      panel.style.display = 'block';
      panel.classList.add('is-open');
    } else {
      panel.hidden = true;
      panel.setAttribute('hidden', 'hidden');
      panel.style.display = 'none';
      panel.classList.remove('is-open');
    }
  }
  function closeAll() {
    document.querySelectorAll('[data-cmb-open-bid-panel], .cmb-open-bid-panel').forEach(function (p) {
      showPanel(p, false);
    });
  }
  function panelFor(el) {
    var card = el.closest('[data-cmb-open-bid-card], .cmb-open-bid-card');
    if (!card) return el.closest('[data-cmb-open-bid-panel], .cmb-open-bid-panel');
    return card.querySelector('[data-cmb-open-bid-panel], .cmb-open-bid-panel');
  }
  function field(panel, sels) {
    var i, el;
    for (i = 0; i < sels.length; i++) {
      el = panel.querySelector(sels[i]);
      if (el) return String(el.value || '').trim();
    }
    return '';
  }
  function submitBid(btn) {
    var panel = panelFor(btn);
    var jobId = (btn.getAttribute('data-job-id') || (panel && panel.getAttribute('data-job-id')) || '').trim();
    if (!jobId || !panel) {
      toast('error', '의뢰를 찾을 수 없습니다.');
      return;
    }
    var amountRaw = field(panel, ['[data-cmb-bid-amount]', '.cmb-open-bid-amount', 'input[name="amount"]']);
    var daysRaw = field(panel, ['[data-cmb-bid-days]', '.cmb-open-bid-days', 'input[name="days"]']);
    var message = field(panel, ['[data-cmb-bid-message]', '.cmb-open-bid-message', 'textarea[name="message"]', 'input[name="message"]']);
    var amountNum = parseInt(String(amountRaw).replace(/[^\d]/g, ''), 10);
    if (!amountNum || amountNum < 1) {
      toast('error', '견적 금액을 입력해 주세요.');
      return;
    }
    var body = { amount: amountNum };
    if (daysRaw !== '') {
      var daysNum = parseInt(String(daysRaw).replace(/[^\d]/g, ''), 10);
      if (!daysNum || daysNum < 1) {
        toast('error', '제작 일수를 확인해 주세요.');
        return;
      }
      body.days = daysNum;
    }
    if (message) body.message = message;
    btn.disabled = true;
    var headers = {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    };
    var token = csrf();
    if (token) {
      headers['X-CSRF-TOKEN'] = token;
      headers['X-XSRF-TOKEN'] = token;
    }
    fetch('/api/modules/custom-maker_bids/jobs/' + encodeURIComponent(jobId) + '/bids', {
      method: 'POST',
      credentials: 'same-origin',
      headers: headers,
      body: JSON.stringify(body)
    }).then(function (res) {
      return res.json().catch(function () { return null; }).then(function (json) {
        return { ok: res.ok, status: res.status, json: json };
      });
    }).then(function (r) {
      btn.disabled = false;
      if (!r.ok) {
        var msg = (r.json && (r.json.message || (r.json.error && r.json.error.message))) || '견적 등록에 실패했습니다.';
        if (r.status === 401) msg = '로그인이 필요합니다.';
        toast('error', msg);
        return;
      }
      toast('success', '견적을 등록했습니다.');
      showPanel(panel, false);
      dispatch('refetchDataSource', { dataSourceId: 'jobs' });
      dispatch('refetchDataSource', { dataSourceId: 'mine' });
    }).catch(function () {
      btn.disabled = false;
      toast('error', '견적 등록에 실패했습니다.');
    });
  }
  function onClick(e) {
    var t = e.target;
    if (!t || !t.closest) return;
    var toggle = t.closest('[data-cmb-open-bid-toggle], .cmb-open-bid-toggle');
    if (toggle) {
      e.preventDefault();
      e.stopPropagation();
      var panel = panelFor(toggle);
      if (!panel) return;
      var wasOpen = panel.classList.contains('is-open');
      closeAll();
      if (!wasOpen) {
        showPanel(panel, true);
        var amount = panel.querySelector('[data-cmb-bid-amount], input[name="amount"]');
        if (amount) try { amount.focus(); } catch (err) {}
      }
      return;
    }
    if (t.closest('[data-cmb-open-bid-cancel], .cmb-open-bid-cancel')) {
      e.preventDefault();
      e.stopPropagation();
      showPanel(panelFor(t), false);
      return;
    }
    var submit = t.closest('[data-cmb-open-bid-submit], .cmb-open-bid-submit');
    if (submit) {
      e.preventDefault();
      e.stopPropagation();
      submitBid(submit);
    }
  }
  if (!document.documentElement.getAttribute('data-cmb-open-bid-bound')) {
    document.documentElement.setAttribute('data-cmb-open-bid-bound', '1');
    document.addEventListener('click', onClick, true);
  }
})();


/* cmb-list-cards: ensure form.css + visible card classes on list rows */
(function () {
  var FORM_CSS = '/api/modules/custom-maker_bids/assets/form.css?v=0.10.15';
  var ITEM_RE = /(^|\s)(cmb-job-card|cmb-bid-card|cmb-company-card|cmb-list-item|cmb-section-card|cmb-empty|cmb-pager)(\s|$)/;

  function ensureFormCss() {
    var existing = document.querySelector('link[href*="custom-maker_bids/assets/form.css"]');
    if (existing) {
      if (existing.href && existing.href.indexOf('v=0.10.15') < 0) {
        existing.href = FORM_CSS;
      }
      return;
    }
    if (document.getElementById('cmb-form-css-page')) return;
    var link = document.createElement('link');
    link.id = 'cmb-form-css-page';
    link.rel = 'stylesheet';
    link.href = FORM_CSS;
    document.head.appendChild(link);
  }

  function ensureItemClass(el) {
    if (!el || el.nodeType !== 1) return;
    var cls = el.getAttribute('class') || '';
    if (ITEM_RE.test(cls)) {
      if (cls.indexOf('cmb-list-item') < 0 && /(cmb-job-card|cmb-bid-card|cmb-company-card)/.test(cls)) {
        el.classList.add('cmb-list-item');
      }
      return;
    }
    // Bare row inside a card list → force a job-card box
    el.classList.add('cmb-job-card', 'cmb-list-item');
  }

  function scan() {
    ensureFormCss();
    document.querySelectorAll('.cmb-card-list').forEach(function (list) {
      Array.prototype.forEach.call(list.children, ensureItemClass);
    });
    document.querySelectorAll('.cmb-section-card').forEach(function (sec) {
      var cls = sec.getAttribute('class') || '';
      if (cls.indexOf('cmb-section-card') >= 0) {
        /* already boxed via CSS */
      }
    });
  }

  scan();
  document.addEventListener('DOMContentLoaded', scan);
  setTimeout(scan, 200);
  setTimeout(scan, 800);
  setTimeout(scan, 1600);
  if (!window.__cmbListCardObs) {
    window.__cmbListCardObs = new MutationObserver(function () { scan(); });
    try {
      window.__cmbListCardObs.observe(document.documentElement, { childList: true, subtree: true });
    } catch (e) {}
  }
})();

/* cmb-export: printable 의뢰서/견적서 HTML → print-to-PDF */
(function () {
  function jobIdFromWorkPath() {
    var m = String(location.pathname || '').match(/\/maker-bids\/(\d+)(?:\/work)?\/?$/);
    return m ? m[1] : '';
  }
  function openHtml(html) {
    var w = window.open('', '_blank');
    if (!w) {
      if (window.G7Core && window.G7Core.dispatch) {
        window.G7Core.dispatch({ handler: 'toast', params: { type: 'warning', message: '팝업이 차단되었습니다. 팝업을 허용해 주세요.' } });
      }
      return;
    }
    w.document.open();
    w.document.write(html);
    w.document.close();
  }
  function fetchExport(doc) {
    var id = jobIdFromWorkPath();
    if (!id) return;
    var url = '/api/modules/custom-maker_bids/jobs/' + id + '/export?doc=' + encodeURIComponent(doc || 'all');
    function handlePayload(payload) {
      var d = payload && payload.data !== undefined ? payload.data : payload;
      if (!d) return;
      if (d.html) {
        openHtml(d.html);
        return;
      }
      if (d.documents && d.documents[0] && d.documents[0].html) {
        openHtml(d.documents[0].html);
      }
    }
    if (window.G7Core && window.G7Core.api && typeof window.G7Core.api.get === 'function') {
      window.G7Core.api.get(url).then(handlePayload).catch(function () {
        if (window.G7Core && window.G7Core.dispatch) {
          window.G7Core.dispatch({ handler: 'toast', params: { type: 'error', message: '문서를 불러오지 못했습니다.' } });
        }
      });
      return;
    }
    fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(handlePayload)
      .catch(function () {});
  }
  function onClick(e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-cmb-export]') : null;
    if (!btn) return;
    e.preventDefault();
    fetchExport(btn.getAttribute('data-cmb-export') || 'all');
  }
  if (!window.__cmbExportBound) {
    window.__cmbExportBound = true;
    document.addEventListener('click', onClick, true);
  }
})();
