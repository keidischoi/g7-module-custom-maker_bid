(function () {
  var DAUM_SRC = 'https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js';
  var TYPES_URL = '/api/modules/custom-maker_bids/job-types';
  var FORM_CSS = '/api/modules/custom-maker_bids/assets/form.css?v=0.10.18';
  var DAY_FROM = '09:00';
  var DAY_TO = '17:00';
  var EXT_KEYS = ['ext_stl', 'ext_obj', 'ext_3mf', 'ext_fbx', 'ext_pdf', 'ext_step', 'ext_stp', 'ext_gcode', 'ext_dwg'];
  var TYPE_FALLBACK = [
    { value: 'modeling_3d', slug: 'modeling_3d', label: '3D 모델링', name: '3D 모델링', requires_address: false, is_design_only: true, includes_modeling: true },
    { value: 'print_3d', slug: 'print_3d', label: '3D 출력 대행', name: '3D 출력 대행', requires_address: true, is_design_only: false, includes_modeling: false },
    { value: 'full_package', slug: 'full_package', label: '풀 패키지 제작 (모델링 + 출력 + 후가공)', name: '풀 패키지 제작 (모델링 + 출력 + 후가공)', requires_address: true, is_design_only: false, includes_modeling: true },
    { value: 'character_figure', slug: 'character_figure', label: '캐릭터·피규어 커미션', name: '캐릭터·피규어 커미션', requires_address: true, is_design_only: false, includes_modeling: true },
    { value: 'design_mockup', slug: 'design_mockup', label: '디자인 목업(Mock-up) 및 시제품', name: '디자인 목업(Mock-up) 및 시제품', requires_address: false, is_design_only: true, includes_modeling: false },
    { value: 'working_prototype', slug: 'working_prototype', label: '워킹 프로토타입(기능성 시제품)', name: '워킹 프로토타입(기능성 시제품)', requires_address: true, is_design_only: false, includes_modeling: true }
  ];
  var typesCache = null;
  var daumLoading = false;
  var applyingDaytime = false;

  function dispatch(handler, params) {
    if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
      window.G7Core.dispatch({ handler: handler, params: params || {} });
    }
  }

  function setLocal(map) {
    var params = { target: 'local' };
    var key;
    for (key in map) {
      if (Object.prototype.hasOwnProperty.call(map, key)) {
        params[key] = map[key];
      }
    }
    dispatch('setState', params);
  }

  function g7Get(path) {
    try {
      if (window.G7Core && window.G7Core.state && typeof window.G7Core.state.get === 'function') {
        return window.G7Core.state.get(path);
      }
    } catch (e) {}
    return null;
  }

  function extractTypesList(body) {
    if (!body) {
      return [];
    }
    if (Object.prototype.toString.call(body) === '[object Array]') {
      return body;
    }
    var d = body.data;
    if (Object.prototype.toString.call(d) === '[object Array]') {
      return d;
    }
    if (d && Object.prototype.toString.call(d.data) === '[object Array]') {
      return d.data;
    }
    if (d && Object.prototype.toString.call(d.types) === '[object Array]') {
      return d.types;
    }
    if (Object.prototype.toString.call(body.types) === '[object Array]') {
      return body.types;
    }
    return [];
  }

  function typeToken(row) {
    if (row == null) {
      return '';
    }
    if (typeof row !== 'object') {
      return asTypeSlug(row);
    }
    return asTypeSlug(row.value != null ? row.value : row.slug != null ? row.slug : row.id);
  }

  function typeLabelOf(row, fallback) {
    if (row && typeof row === 'object') {
      return String(row.label || row.name || fallback || typeToken(row) || '');
    }
    return String(fallback || row || '');
  }

  function asTypeSlug(raw) {
    if (raw == null || raw === '') return '';
    if (typeof raw === 'object') {
      var v = raw.value != null ? raw.value : raw.slug != null ? raw.slug : raw.id != null ? raw.id : '';
      if (typeof v === 'object') return asTypeSlug(v);
      return String(v == null ? '' : v).trim();
    }
    var s = String(raw).trim();
    if (!s || s === '[object Object]' || s === 'Array' || /^\[object\s/i.test(s)) return '';
    return s;
  }

  // EXT_NORMALIZE_START
  var EXT_FALLBACK = ['STL', 'OBJ', '3MF', 'FBX', 'PDF', 'STEP', 'STP', 'GCODE', 'DWG'];
  var EXT_KNOWN = {
    STL: 1, OBJ: 1, '3MF': 1, FBX: 1, PDF: 1, STEP: 1, STP: 1, GCODE: 1, DWG: 1,
    GLB: 1, GLTF: 1, IGES: 1, IGS: 1, PLY: 1, AMF: 1, DAE: 1, BLEND: 1, ZIP: 1, RAR: 1, '7Z': 1
  };
  var EXT_TOKEN_JUNK = {
    TRUE: 1, FALSE: 1, YES: 1, NO: 1, ON: 1, OFF: 1, NULL: 1, UNDEFINED: 1,
    KRW: 1, USD: 1, EUR: 1, JPY: 1, CNY: 1, GBP: 1,
    KR: 1, US: 1, EN: 1, JP: 1, CN: 1, KO: 1
  };

  function isExtLikeToken(s) {
    return typeof s === 'string' && /^[A-Z0-9]{2,16}$/.test(s) && !EXT_TOKEN_JUNK[s] && !/^\d+$/.test(s);
  }

  function isBoolishExtValue(v) {
    if (v === true || v === false || v === 0 || v === 1) return true;
    if (v === '0' || v === '1') return true;
    if (typeof v === 'string') {
      var u = v.trim().toLowerCase();
      return u === 'on' || u === 'off' || u === 'true' || u === 'false' || u === 'yes' || u === 'no';
    }
    return false;
  }

  function isTruthyExtValue(v) {
    if (v === true || v === 1 || v === '1') return true;
    if (typeof v === 'string') {
      var u = v.trim().toLowerCase();
      return u === 'on' || u === 'true' || u === 'yes';
    }
    return false;
  }

  function isSingleExtOption(obj) {
    return !!(obj && typeof obj === 'object' && !Array.isArray(obj)
      && (obj.value != null || obj.label != null || obj.ext != null || obj.slug != null));
  }

  function looksLikeExtMap(obj) {
    if (!obj || typeof obj !== 'object' || Array.isArray(obj)) return false;
    var keys = Object.keys(obj);
    if (!keys.length) return false;
    var i, k, tok;
    for (i = 0; i < keys.length; i++) {
      k = keys[i];
      tok = String(k || '').replace(/^\./, '').trim().toUpperCase();
      if (!isExtLikeToken(tok) || !isBoolishExtValue(obj[k])) return false;
    }
    return true;
  }

  function asExtToken(raw) {
    if (raw == null || raw === '') return '';
    var s = '';
    if (typeof raw === 'object') {
      if (Array.isArray(raw)) return '';
      var v = raw.value != null ? raw.value : raw.label != null ? raw.label : raw.ext != null ? raw.ext : raw.slug != null ? raw.slug : '';
      if (typeof v === 'object') return asExtToken(v);
      s = String(v == null ? '' : v);
    } else {
      s = String(raw);
    }
    s = s.replace(/^\./, '').trim().toUpperCase();
    if (!s || s.indexOf('[OBJECT') === 0 || s === 'OBJECT]' || s === '[OBJECT OBJECT]' || /^\[OBJECT/.test(s)) return '';
    if (s.length < 2) return '';
    if (/^\d+$/.test(s)) return '';
    if (EXT_TOKEN_JUNK[s]) return '';
    if (!/^[A-Z0-9]{2,16}$/.test(s)) return '';
    return s;
  }

  function extractExtTokensFromObject(raw) {
    var t;
    if (looksLikeExtMap(raw)) {
      var keys = Object.keys(raw);
      var out = [];
      var i;
      for (i = 0; i < keys.length; i++) {
        if (!isTruthyExtValue(raw[keys[i]])) continue;
        t = asExtToken(keys[i]);
        if (t) out.push(t);
      }
      return out;
    }
    if (isSingleExtOption(raw)) {
      t = asExtToken(raw);
      return t ? [t] : [];
    }
    return [];
  }

  function listLooksPolluted(list) {
    var i, t;
    for (i = 0; i < list.length; i++) {
      t = list[i];
      if (!t || EXT_TOKEN_JUNK[t] || !EXT_KNOWN[t]) return true;
    }
    return false;
  }

  function normalizeExtList(raw) {
    var out = [];
    var seen = {};
    var push = function (tok) {
      tok = asExtToken(tok);
      if (!tok || seen[tok]) return;
      seen[tok] = 1;
      out.push(tok);
    };
    if (raw == null || raw === '') return EXT_FALLBACK.slice();
    if (Array.isArray(raw)) {
      raw.forEach(function (item) {
        if (item != null && typeof item === 'object' && !Array.isArray(item)) {
          extractExtTokensFromObject(item).forEach(push);
        } else {
          push(item);
        }
      });
    } else if (typeof raw === 'string') {
      String(raw).split(/[\s,;|]+/).forEach(push);
    } else if (typeof raw === 'object') {
      extractExtTokensFromObject(raw).forEach(push);
    } else {
      push(raw);
    }
    if (!out.length || listLooksPolluted(out)) return EXT_FALLBACK.slice();
    return out;
  }
  // EXT_NORMALIZE_END

  function normalizeTypeOption(row) {
    if (row == null) return null;
    if (typeof row !== 'object') {
      var s = asTypeSlug(row);
      if (!s) return null;
      return { value: s, slug: s, label: s, name: s };
    }
    var value = asTypeSlug(row.value != null ? row.value : row.slug != null ? row.slug : row.id);
    var label = String(row.label || row.name || value || '').trim();
    if (!value && label) value = asTypeSlug(label);
    if (!value && !label) return null;
    var out = {};
    var k;
    for (k in row) {
      if (Object.prototype.hasOwnProperty.call(row, k)) out[k] = row[k];
    }
    out.value = value || '';
    out.slug = asTypeSlug(row.slug) || value || '';
    out.label = label || value || '';
    out.name = String(row.name || label || value || '');
    return out;
  }

  function setNativeValue(el, value) {
    if (!el) {
      return;
    }
    var tag = (el.tagName || '').toUpperCase();
    if (el.type === 'file' || el.type === 'checkbox' || el.type === 'radio') {
      return;
    }
    if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
      try {
        el.value = value;
      } catch (e) {}
      return;
    }
    var proto =
      tag === 'TEXTAREA'
        ? window.HTMLTextAreaElement.prototype
        : tag === 'SELECT'
          ? window.HTMLSelectElement.prototype
          : window.HTMLInputElement.prototype;
    var desc = proto && Object.getOwnPropertyDescriptor(proto, 'value');
    if (desc && desc.set) {
      desc.set.call(el, value);
    } else {
      el.value = value;
    }
    try {
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (e2) {}
  }

  function firePointerClick(el) {
    if (!el) {
      return;
    }
    var opts = { bubbles: true, cancelable: true, view: window, buttons: 1, composed: true };
    try {
      if (window.PointerEvent) {
        el.dispatchEvent(new PointerEvent('pointerdown', opts));
      }
    } catch (e) {}
    try {
      el.dispatchEvent(new MouseEvent('mousedown', opts));
    } catch (e2) {}
    try {
      if (window.PointerEvent) {
        el.dispatchEvent(new PointerEvent('pointerup', opts));
      }
    } catch (e3) {}
    try {
      el.dispatchEvent(new MouseEvent('mouseup', opts));
    } catch (e4) {}
    try {
      el.dispatchEvent(new MouseEvent('click', opts));
    } catch (e5) {}
    try {
      if (typeof el.click === 'function') {
        el.click();
      }
    } catch (e6) {}
  }

  function fillNamed(name, value) {
    var nodes = document.querySelectorAll('[name="' + name + '"]');
    var i;
    var el;
    for (i = 0; i < nodes.length; i++) {
      el = nodes[i];
      if (el.type === 'checkbox' || el.type === 'radio' || el.type === 'file') {
        continue;
      }
      setNativeValue(el, value);
    }
  }

  function localFormKey() {
    if (document.querySelector('[data-cmb-company-form]')) {
      return 'company';
    }
    return 'form';
  }

  var STATUS_SLUGS = {
    quote_request: 'quote_request',
    open: 'quote_request',
    request: 'request',
    hold: 'hold',
    draft: 'draft',
    '견적요청': 'quote_request',
    '의뢰': 'request',
    '보류': 'hold',
    '임시저장': 'draft',
    '초안': 'draft'
  };

  function normalizeStatusSlug(raw) {
    var v = String(raw == null ? '' : raw).trim();
    if (!v) return 'quote_request';
    if (STATUS_SLUGS[v]) return STATUS_SLUGS[v];
    var lower = v.toLowerCase();
    if (STATUS_SLUGS[lower]) return STATUS_SLUGS[lower];
    if (['quote_request', 'request', 'hold', 'draft'].indexOf(lower) !== -1) return lower;
    return 'quote_request';
  }

  function normalizeAudienceSlug(raw) {
    var v = String(raw == null ? '' : raw).trim();
    if (!v) return 'all';
    if (v === '업체만' || v === '업체' || v === 'company') return 'company';
    if (v === '개인만' || v === '개인' || v === 'individual') return 'individual';
    if (v === '관리자' || v === '관리자만' || v === 'admin') return 'admin';
    if (v === '전체' || v === 'all') return 'all';
    return 'all';
  }

  function collectCheckedExtensions() {
    var allowed = providedExtensionsFromState();
    var out = [];
    var seen = {};
    allowed.forEach(function (ext) {
      var key = extFieldKey(ext);
      var on = false;
      var cur = g7Get('_local.form.' + key);
      if (typeof cur === 'boolean') on = cur;
      else {
        var el = document.querySelector('[name="' + key + '"]');
        on = !!(el && (el.checked || el.value === '1' || el.value === 'true'));
      }
      if (on) {
        var up = String(ext).toUpperCase();
        if (!seen[up]) {
          seen[up] = 1;
          out.push(up);
        }
      }
    });
    return out;
  }

  /** Build a clean create/update payload: slug status, never Korean labels. */
  function collectCreateJobPayload() {
    harvestNamedFields();
    var form = g7Get('_local.form') || {};
    var status = normalizeStatusSlug(form.status || readNamedValue('status') || 'quote_request');
    var audience = normalizeAudienceSlug(form.audience || readNamedValue('audience') || 'all');
    var type = asTypeSlug(form.type) || asTypeSlug(readNamedValue('type')) || '';
    var typeRow = findTypeRow(type);
    if (typeRow) {
      type = asTypeSlug(typeRow.value || typeRow.slug) || type;
    }
    var exts = collectCheckedExtensions();
    var map = {
      'form.status': status,
      'form.audience': audience,
      'form.provided_extensions': exts
    };
    if (type) map['form.type'] = type;
    exts.forEach(function (ext) {
      map['form.' + extFieldKey(ext)] = true;
    });
    setLocal(map);
    fillNamed('status', status);
    return Object.assign({}, g7Get('_local.form') || {}, {
      status: status,
      audience: audience,
      type: type,
      provided_extensions: exts
    });
  }


  function harvestNamedFields() {
    var prefix = localFormKey();
    var map = {};
    var nodes = document.querySelectorAll('.cmb-order-card [name], [data-cmb-company-form] [name], .cmb-company-form [name]');
    var i;
    var el;
    var name;
    var tag;
    var val;
    var selectKeys = { type: 1, status: 1, audience: 1, kind: 1, nav_insert: 1, bid_allow: 1, default_job_status: 1 };
    var cur = g7Get('_local.' + prefix) || {};
    for (i = 0; i < nodes.length; i++) {
      el = nodes[i];
      name = el.getAttribute('name');
      if (!name || el.type === 'file') {
        continue;
      }
      tag = (el.tagName || '').toUpperCase();
      if (el.type === 'checkbox') {
        // Prefer bound _local state — DOM checked can be stale vs G7 bindings.
        if (cur && typeof cur[name] === 'boolean') {
          map[prefix + '.' + name] = cur[name];
        } else {
          map[prefix + '.' + name] = !!el.checked;
        }
        continue;
      }
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        val = el.value;
        // G7 Select wrappers often leave an empty hidden/input — do not clobber local slug.
        if (selectKeys[name] && String(val || '').trim() === '') {
          continue;
        }
        // Unbound/empty DOM must not wipe edit values before PATCH (image-only save).
        if (String(val || '').trim() === '') {
          continue;
        }
        map[prefix + '.' + name] = val;
      }
    }
    // Prefer live select value / G7 state for critical slugs.
        ['type', 'status', 'audience', 'kind'].forEach(function (k) {
      var live = readNamedValue(k);
      var curVal = cur && cur[k] != null ? cur[k] : '';
      if (k === 'type') {
        live = asTypeSlug(live);
        curVal = asTypeSlug(curVal);
      }
      if (live && String(live).trim() !== '') {
        map[prefix + '.' + k] = live;
      } else if (curVal !== '' && String(curVal).trim() !== '') {
        map[prefix + '.' + k] = curVal;
      }
    });
    if (prefix === 'form') {
      if (map['form.status'] != null) {
        map['form.status'] = normalizeStatusSlug(map['form.status']);
      } else if (cur && cur.status != null) {
        map['form.status'] = normalizeStatusSlug(cur.status);
      }
      if (map['form.audience'] != null) {
        map['form.audience'] = normalizeAudienceSlug(map['form.audience']);
      }
      var row = findTypeRow(asTypeSlug(map['form.type']) || asTypeSlug(cur && cur.type) || '');
      if (row) {
        map['form.type'] = asTypeSlug(row.value || row.slug) || asTypeSlug(map['form.type']) || '';
      } else if (map['form.type'] != null) {
        map['form.type'] = asTypeSlug(map['form.type']);
      }
      try {
        var exts = collectCheckedExtensions();
        map['form.provided_extensions'] = exts;
      } catch (eExt) {}
    }
    if (Object.keys(map).length) {
      setLocal(map);
    }
  }

  function bindFieldSync() {
    var card = document.querySelector('.cmb-order-card');
    if (!card || card.getAttribute('data-cmb-sync-bound')) {
      return;
    }
    card.setAttribute('data-cmb-sync-bound', '1');
    var on = function (e) {
      var el = e.target;
      if (!el || !el.getAttribute) {
        return;
      }
      var name = el.getAttribute('name');
      if (!name || el.type === 'file' || el.type === 'checkbox') {
        return;
      }
      var tag = (el.tagName || '').toUpperCase();
      if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') {
        return;
      }
      var map = {};
      var syncVal = el.value;
      if (name === 'type') {
        syncVal = asTypeSlug(syncVal) || asTypeSlug(g7Get('_local.form.type')) || syncVal;
      }
      map[localFormKey() + '.' + name] = syncVal;
      setLocal(map);
    };
    card.addEventListener('input', on, true);
    card.addEventListener('change', on, true);
  }

  function bindHarvest() {
    var btns = document.querySelectorAll('.cmb-order-submit, [data-cmb-company-submit]');
    var i;
    for (i = 0; i < btns.length; i++) {
      if (btns[i].getAttribute('data-cmb-harvest')) {
        continue;
      }
      btns[i].setAttribute('data-cmb-harvest', '1');
      btns[i].addEventListener('click', function () {
        if (document.querySelector('.cmb-order-card') && !document.querySelector('[data-cmb-company-form]')) {
          try { collectCreateJobPayload(); } catch (eH) { harvestNamedFields(); }
        } else {
          harvestNamedFields();
        }
      }, true);
    }
  }

  function companyMeRecord() {
    var me = g7Get('me.data') || g7Get('me');
    if (me && me.data && typeof me.data === 'object' && (me.data.status || me.data.id)) {
      me = me.data;
    }
    if (!me || typeof me !== 'object') {
      return null;
    }
    return me;
  }

  function companyMeStatus() {
    var me = companyMeRecord();
    if (!me) {
      return '';
    }
    return String(me.status || '');
  }

  function companyFormEl() {
    return document.querySelector('[data-cmb-company-form], .cmb-company-form');
  }

  function companyFormIsVisible(el) {
    el = el || companyFormEl();
    if (!el) {
      return false;
    }
    if (el.classList.contains('is-collapsed') || el.hasAttribute('hidden')) {
      return false;
    }
    try {
      var st = window.getComputedStyle(el);
      if (st.display === 'none' || st.visibility === 'hidden') {
        return false;
      }
    } catch (e) {}
    return true;
  }

  function prefillCompanyFromMe() {
    var me = companyMeRecord();
    if (!me || !me.status) {
      return;
    }
    var fields = ['kind', 'name', 'business_no', 'bio', 'homepage_url', 'portfolio_url', 'manager_name', 'phone', 'email', 'zipcode', 'address', 'address_detail'];
    var map = {};
    var i;
    var field;
    var value;
    for (i = 0; i < fields.length; i++) {
      field = fields[i];
      value = me[field];
      if (value == null || String(value).trim() === '') {
        continue;
      }
      fillNamed(field, String(value));
      map['company.' + field] = value;
    }
    if (Object.keys(map).length) {
      setLocal(map);
    }
    if (me.kind) {
      paintSelectTrigger('kind', me.kind, optionLabelFor('kind', me.kind));
    }
  }

  function revealCompanyForm() {
    var el = companyFormEl();
    if (!el) {
      return false;
    }
    el.classList.remove('is-collapsed');
    el.removeAttribute('hidden');
    el.style.removeProperty('display');
    el.setAttribute('data-cmb-form-revealed', '1');
    prefillCompanyFromMe();
    try {
      el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch (e) {
      try {
        el.scrollIntoView();
      } catch (e2) {}
    }
    var field = el.querySelector('input:not([type="hidden"]):not([type="checkbox"]), select, textarea, [role="combobox"]');
    if (field && typeof field.focus === 'function') {
      try {
        field.focus();
      } catch (e3) {}
    }
    bindCompanyJobTypes();
    bindProfileFill();
    bindFieldSync();
    return true;
  }

  function syncCompanySubmit() {
    var btn = document.querySelector('[data-cmb-company-submit]');
    var form = companyFormEl();
    var st = companyMeStatus();
    var revealed = !!(form && form.getAttribute('data-cmb-form-revealed') === '1');
    // Approved companies may update profile (status stays approved server-side).
    var lock = false;
    if (form) {
      if (st === 'pending' && !revealed && !companyFormIsVisible(form)) {
        form.classList.add('is-collapsed');
      } else if (st === 'approved' || st === 'rejected' || revealed || st === '') {
        form.classList.remove('is-collapsed');
        if (st === 'approved' || st === 'rejected') {
          prefillCompanyFromMe();
        }
      } else {
        form.classList.remove('is-collapsed');
      }
    }
    if (!btn) {
      return;
    }
    btn.classList.toggle('is-locked', lock);
    btn.removeAttribute('hidden');
    btn.removeAttribute('aria-hidden');
    btn.style.setProperty('display', 'inline-flex', 'important');
    if (st === 'approved') {
      btn.textContent = btn.getAttribute('data-cmb-label-edit') || '업체 정보 수정';
    }
  }

  function bindCompanySubmit() {
    var btn = document.querySelector('[data-cmb-company-submit]');
    if (!btn || btn.getAttribute('data-cmb-reveal-bound')) {
      return;
    }
    btn.setAttribute('data-cmb-reveal-bound', '1');
    btn.addEventListener(
      'click',
      function (e) {
        var form = companyFormEl();
        var visible = companyFormIsVisible(form);
        // First click on collapsed form: reveal only (create / pending / approved edit).
        if (!form || !visible) {
          e.preventDefault();
          e.stopImmediatePropagation();
          revealCompanyForm();
          return;
        }
        // Form visible: harvest and let layout sequence (logo upload + POST) run.
        harvestNamedFields();
        // Ensure upload_token is present for logo attach.
        var token = g7Get('_local.company.upload_token') || g7Get('defaults.data.upload_token') || '';
        if (token) {
          setLocal({ 'company.upload_token': token });
        }
      },
      true
    );
  }

  function pushTypeList(out, seen, list) {
    list = extractTypesList(list);
    var i;
    var row;
    var key;
    for (i = 0; i < list.length; i++) {
      row = normalizeTypeOption(list[i]);
      if (!row) {
        continue;
      }
      key = typeToken(row) + '|' + typeLabelOf(row);
      if (!key || seen[key]) {
        continue;
      }
      seen[key] = true;
      out.push(row);
    }
  }
  function catalogTypes() {
    var out = [];
    var seen = {};
    pushTypeList(out, seen, typesCache);
    pushTypeList(out, seen, g7Get('_data.defaults.data.types'));
    pushTypeList(out, seen, g7Get('_data.types.data'));
    pushTypeList(out, seen, g7Get('defaults.data.types'));
    pushTypeList(out, seen, g7Get('types.data'));
    var defaultsData = g7Get('_data.defaults.data');
    if (defaultsData && defaultsData.types) {
      pushTypeList(out, seen, defaultsData.types);
    }
    if (!out.length) {
      pushTypeList(out, seen, TYPE_FALLBACK);
    }
    return out;
  }

  function findSelectWrap(name) {
    var byId = document.getElementById(name + '_wrap');
    if (byId) {
      return byId;
    }
    var host = document.querySelector('[name="' + name + '"]');
    if (host && host.closest) {
      return host.closest('.cmb-order-select-wrap') || host.parentElement;
    }
    return null;
  }

  function readOptionChoice(el) {
    var label = String((el && (el.textContent || el.getAttribute('label') || '')) || '')
      .replace(/\s+/g, ' ')
      .trim();
    var value = '';
    if (el) {
      value =
        el.getAttribute('data-value') ||
        el.getAttribute('data-option-value') ||
        el.getAttribute('value') ||
        (el.dataset && (el.dataset.value || el.dataset.optionValue)) ||
        '';
      if (!value && el.tagName === 'OPTION') {
        value = el.value || '';
      }
    }
    return { value: String(value || ''), label: label, el: el };
  }

  function listSelectOptionNodes(name) {
    var wrap = findSelectWrap(name);
    var nodes = [];
    var seen = [];
    var add = function (el) {
      if (!el || seen.indexOf(el) !== -1) {
        return;
      }
      seen.push(el);
      nodes.push(el);
    };
    var i;
    var nlist;
    var native;
    if (wrap) {
      nlist = wrap.querySelectorAll('[role="option"], option, [data-slot="select-item"], [data-value]');
      for (i = 0; i < nlist.length; i++) {
        add(nlist[i]);
      }
      native = wrap.querySelector('select');
      if (native && native.options) {
        for (i = 0; i < native.options.length; i++) {
          add(native.options[i]);
        }
      }
    }
    nlist = document.querySelectorAll(
      '[role="listbox"] [role="option"], [data-radix-select-viewport] [role="option"], [data-slot="select-content"] [role="option"], [data-state="open"] [role="option"]'
    );
    for (i = 0; i < nlist.length; i++) {
      add(nlist[i]);
    }
    return nodes;
  }

  function collectLiveChoices(name) {
    var nodes = listSelectOptionNodes(name);
    var out = [];
    var i;
    var c;
    for (i = 0; i < nodes.length; i++) {
      c = readOptionChoice(nodes[i]);
      if (c.value || c.label) {
        out.push(c);
      }
    }
    return out;
  }

  function optionMatchesChoice(choice, value, label) {
    var v = String(value || '');
    var lab = String(label || '').replace(/\s+/g, ' ').trim();
    var cv = String((choice && choice.value) || '');
    var cl = String((choice && choice.label) || '').replace(/\s+/g, ' ').trim();
    if (v && cv && cv === v) {
      return true;
    }
    if (lab && cl && (cl === lab || cl.indexOf(lab) !== -1 || lab.indexOf(cl) !== -1)) {
      return true;
    }
    if (v && cl && cl === v) {
      return true;
    }
    return false;
  }

  function paintSelectTrigger(name, value, label) {
    var wrap = findSelectWrap(name);
    if (name === 'type') {
      value = asTypeSlug(value);
    } else if (value != null && typeof value === 'object') {
      value = asTypeSlug(value) || '';
    } else {
      value = value == null ? '' : String(value);
    }
    var text = label;
    if (text != null && typeof text === 'object') {
      text = typeLabelOf(text) || asTypeSlug(text);
    }
    text = text || optionLabelFor(name, value);
    if (text != null && typeof text === 'object') {
      text = String(text.label || text.name || asTypeSlug(text) || '');
    }
    text = text == null ? '' : String(text);
    if (!wrap || !text) {
      return;
    }
    wrap.setAttribute('data-cmb-selected-value', String(value || ''));
    var btn =
      wrap.querySelector('[role="combobox"]') ||
      wrap.querySelector('[data-slot="select-trigger"]') ||
      wrap.querySelector('button');
    if (!btn) {
      return;
    }
    var txt = btn.querySelector('span:not([class*="icon"]):not(.sr-only)') || btn.querySelector('span') || btn;
    try {
      txt.textContent = text;
    } catch (e) {}
  }

  function closeOpenSelect() {
    try {
      document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', code: 'Escape', keyCode: 27, bubbles: true, cancelable: true }));
    } catch (e) {}
  }

  function openSelectMenu(name, cb) {
    var wrap = findSelectWrap(name);
    var trigger =
      (wrap &&
        (wrap.querySelector('[role="combobox"]') ||
          wrap.querySelector('[data-slot="select-trigger"]') ||
          wrap.querySelector('button') ||
          wrap.querySelector('[name="' + name + '"]'))) ||
      document.querySelector('[name="' + name + '"]');
    var openList = document.querySelector('[role="listbox"], [data-slot="select-content"][data-state="open"]');
    if (!openList && trigger) {
      firePointerClick(trigger);
    }
    setTimeout(function () {
      cb();
    }, 50);
  }

  function optionLabelFor(name, value) {
    var row;
    if (name === 'type') {
      value = asTypeSlug(value);
      row = findTypeRow(value);
      if (row) {
        return String(row.label || row.name || value || '');
      }
      return value;
    }
    if (name === 'status') {
      if (value === 'hold') {
        return '보류';
      }
      if (value === 'request') {
        return '의뢰';
      }
      if (value === 'quote_request' || value === 'open') {
        return '견적요청';
      }
    }
    if (name === 'audience') {
      if (value === 'company') {
        return '업체만';
      }
      if (value === 'individual') {
        return '개인만';
      }
      return '전체';
    }
    if (name === 'kind') {
      if (value === 'individual') {
        return '개인';
      }
      return '업체';
    }
    return value;
  }

  function setG7Select(name, value, done) {
    var prefix = localFormKey();
    var chosen = name === 'type' ? asTypeSlug(value) : String(value == null || typeof value === 'object' ? asTypeSlug(value) || '' : value);
    var label = optionLabelFor(name, chosen);
    var map = {};
    var finish = function () {
      paintSelectTrigger(name, chosen, label);
      if (typeof done === 'function') {
        done();
      }
    };
    var writeState = function (next) {
      chosen = name === 'type' ? asTypeSlug(next || chosen) : String(next || chosen);
      map = {};
      map[prefix + '.' + name] = chosen;
      setLocal(map);
      fillNamed(name, chosen);
    };
    writeState(chosen);
    var host = document.querySelector('[name="' + name + '"]');
    if (host) {
      setNativeValue(host, chosen);
    }
    var native = document.querySelector('select[name="' + name + '"]');
    if (native) {
      native.value = chosen;
      try {
        native.dispatchEvent(new Event('change', { bubbles: true }));
      } catch (e) {}
    }
    var hiddenWrap = findSelectWrap(name);
    if (hiddenWrap) {
      var hidden = hiddenWrap.querySelector('input[type="hidden"]');
      if (hidden) {
        setNativeValue(hidden, chosen);
      }
    }

    var clickMatch = function () {
      var nodes = listSelectOptionNodes(name);
      var i;
      var choice;
      var picked = null;
      for (i = 0; i < nodes.length; i++) {
        choice = readOptionChoice(nodes[i]);
        if (optionMatchesChoice(choice, chosen, label)) {
          picked = nodes[i];
          if (choice.value) {
            writeState(choice.value);
          }
          if (choice.label) {
            label = choice.label;
          }
          break;
        }
      }
      if (picked) {
        picked.setAttribute('aria-selected', 'true');
        firePointerClick(picked);
        setTimeout(function () {
          writeState(chosen);
          closeOpenSelect();
          finish();
        }, 40);
        return true;
      }
      return false;
    };

    if (clickMatch()) {
      return;
    }
    openSelectMenu(name, function () {
      if (!clickMatch()) {
        closeOpenSelect();
        finish();
      }
    });
  }

  function fillCheck(name, checked) {
    checked = !!checked;
    var map = {};
    map[localFormKey() + '.' + name] = checked;
    setLocal(map);
    var nodes = document.querySelectorAll('[name="' + name + '"]');
    var i;
    var el;
    var inner;
    var target;
    var isOn;
    for (i = 0; i < nodes.length; i++) {
      el = nodes[i];
      inner = el.type === 'checkbox' ? el : el.querySelector && el.querySelector('input[type="checkbox"]');
      target =
        inner ||
        (el.getAttribute && el.getAttribute('role') === 'checkbox' ? el : null) ||
        (el.querySelector && (el.querySelector('[role="checkbox"]') || el.querySelector('button'))) ||
        el;
      isOn = inner
        ? !!inner.checked
        : el.getAttribute('aria-checked') === 'true' || el.getAttribute('data-state') === 'checked';
      if (isOn !== checked) {
        firePointerClick(target);
      }
      if (inner) {
        inner.checked = checked;
        try {
          inner.dispatchEvent(new Event('input', { bubbles: true }));
          inner.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (e) {}
      }
    }
  }

  function pad2(n) {
    return n < 10 ? '0' + n : String(n);
  }

  function normalizeTime(value) {
    return String(value || '').slice(0, 5);
  }

  function loadDaum(cb) {
    if (window.daum && window.daum.Postcode) {
      cb();
      return;
    }
    if (daumLoading) {
      setTimeout(function () {
        loadDaum(cb);
      }, 200);
      return;
    }
    daumLoading = true;
    var s = document.createElement('script');
    s.src = DAUM_SRC;
    s.async = true;
    s.onload = function () {
      daumLoading = false;
      cb();
    };
    s.onerror = function () {
      daumLoading = false;
      dispatch('toast', { type: 'error', message: '주소 검색을 불러오지 못했습니다.' });
    };
    document.head.appendChild(s);
  }

  function openPostcode() {
    loadDaum(function () {
      new window.daum.Postcode({
        oncomplete: function (data) {
          var zip = data.zonecode || '';
          var addr = data.roadAddress || data.jibunAddress || data.address || '';
          fillNamed('zipcode', zip);
          fillNamed('address', addr);
          var addrMap = {};
          addrMap[localFormKey() + '.zipcode'] = zip;
          addrMap[localFormKey() + '.address'] = addr;
          setLocal(addrMap);
        }
      }).open();
    });
  }

  function findTypeRow(slug) {
    var list = catalogTypes();
    var i;
    var row;
    var s = asTypeSlug(slug);
    for (i = 0; i < list.length; i++) {
      row = list[i];
      if (
        String(row.value) === s ||
        String(row.slug) === s ||
        String(row.id) === s ||
        String(row.label) === s ||
        String(row.name) === s
      ) {
        return row;
      }
    }
    return null;
  }

  function rowIncludesModeling(row, slug) {
    if (row && row.includes_modeling !== undefined && row.includes_modeling !== null && row.includes_modeling !== '') {
      return row.includes_modeling === true || row.includes_modeling === 1 || row.includes_modeling === '1';
    }
    var hay = String((row && (row.slug || row.value || '')) || slug || '') + ' ' + String((row && (row.name || row.label)) || '');
    if (/print_3d|출력\s*대행|design_mockup|목업/.test(hay)) {
      return false;
    }
    return /modeling|모델링|full_package|풀\s*패키지|character_figure|커미션|working_prototype|워킹\s*프로토타입/.test(hay);
  }

  function syncExtVisibility(show) {
    var wrap = document.querySelector('[data-cmb-ext], .cmb-cond-ext');
    if (wrap) {
      if (show) {
        wrap.classList.add('is-open');
      } else {
        wrap.classList.remove('is-open');
      }
    }
    if (show) {
      return;
    }
    var i;
    var localMap = {};
    for (i = 0; i < EXT_KEYS.length; i++) {
      fillCheck(EXT_KEYS[i], false);
      localMap['form.' + EXT_KEYS[i]] = false;
    }
    setLocal(localMap);
  }

  function applyTypeFlags(slug) {
    slug = asTypeSlug(slug);
    if (!slug) {
      syncExtVisibility(false);
      return;
    }
    var row = findTypeRow(slug);
    if (!row && !typesCache) {
      loadTypes(function () {
        applyTypeFlags(slug);
      });
      return;
    }
    var needs = !row || (row.requires_address !== false && row.is_design_only !== true);
    var modeling = rowIncludesModeling(row, slug);
    setLocal({
      'form.requires_address': needs ? '1' : '0',
      'form.includes_modeling': modeling ? '1' : '0'
    });
    syncExtVisibility(modeling);
    syncProvidedExtOptions();
  }

  function loadTypes(cb) {
    if (typesCache && typesCache.length) {
      cb();
      return;
    }
    var fromState = [];
    var seen = {};
    pushTypeList(fromState, seen, g7Get('_data.defaults.data.types'));
    pushTypeList(fromState, seen, g7Get('_data.types.data'));
    pushTypeList(fromState, seen, g7Get('defaults.data.types'));
    pushTypeList(fromState, seen, g7Get('types.data'));
    if (fromState.length) {
      typesCache = fromState;
      cb();
      return;
    }
    fetch(TYPES_URL, { headers: { Accept: 'application/json' } })
      .then(function (res) {
        return res.json();
      })
      .then(function (body) {
        typesCache = extractTypesList(body);
        if (!typesCache.length) {
          typesCache = TYPE_FALLBACK;
        }
        cb();
      })
      .catch(function () {
        typesCache = TYPE_FALLBACK;
        cb();
      });
  }

  function ensureThemeCss() {
    if (document.getElementById('cmb-order-form-css')) {
      return;
    }
    var link = document.createElement('link');
    link.id = 'cmb-order-form-css';
    link.rel = 'stylesheet';
    link.href = FORM_CSS;
    document.head.appendChild(link);
  }

  function daytimeBox() {
    return document.querySelector('[data-cmb-daytime]');
  }

  function applyDaytimeHours() {
    applyingDaytime = true;
    fillNamed('contact_hours_from', DAY_FROM);
    fillNamed('contact_hours_to', DAY_TO);
    setLocal({
      'form.contact_hours_from': DAY_FROM,
      'form.contact_hours_to': DAY_TO
    });
    applyingDaytime = false;
  }

  function uncheckDaytime() {
    var box = daytimeBox();
    if (!box || !box.checked) {
      return;
    }
    box.checked = false;
    try {
      box.dispatchEvent(new Event('input', { bubbles: true }));
      box.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (e) {}
  }

  function bindDaytime() {
    var box = daytimeBox();
    var from = document.querySelector('[name="contact_hours_from"]');
    var to = document.querySelector('[name="contact_hours_to"]');
    if (!box || !from || !to) {
      return;
    }
    if (!box.getAttribute('data-cmb-bound')) {
      box.setAttribute('data-cmb-bound', '1');
      box.addEventListener('change', function () {
        if (box.checked) {
          applyDaytimeHours();
        }
      });
    }
    if (!from.getAttribute('data-cmb-daytime-bound')) {
      from.setAttribute('data-cmb-daytime-bound', '1');
      to.setAttribute('data-cmb-daytime-bound', '1');
      var onManual = function () {
        if (applyingDaytime) {
          return;
        }
        if (normalizeTime(from.value) !== DAY_FROM || normalizeTime(to.value) !== DAY_TO) {
          uncheckDaytime();
        }
      };
      from.addEventListener('input', onManual);
      from.addEventListener('change', onManual);
      to.addEventListener('input', onManual);
      to.addEventListener('change', onManual);
    }
    if (normalizeTime(from.value) === DAY_FROM && normalizeTime(to.value) === DAY_TO) {
      box.checked = true;
    }
  }

  function findCheck(name) {
    return document.querySelector('input[type="checkbox"][name="' + name + '"]') || document.querySelector('[name="' + name + '"]');
  }

  function isChecked(el) {
    if (!el) {
      return false;
    }
    if (el.type === 'checkbox') {
      return !!el.checked;
    }
    var inner = el.querySelector && el.querySelector('input[type="checkbox"]');
    return !!(inner && inner.checked);
  }

  function syncCond(name, wrapSel) {
    var box = findCheck(name);
    var wrap = document.querySelector(wrapSel);
    if (!wrap) {
      return;
    }
    var on = isChecked(box);
    if (on) {
      wrap.classList.add('is-open');
    } else {
      wrap.classList.remove('is-open');
    }
    var fields = wrap.querySelectorAll('input, select, textarea');
    var i;
    for (i = 0; i < fields.length; i++) {
      fields[i].disabled = !on;
    }
  }

  function syncAudienceSection() {
    var sec = document.querySelector('[data-cmb-audience-section], .cmb-audience-section, #sec_audience');
    if (!sec) {
      return;
    }
    sec.removeAttribute('hidden');
    sec.style.removeProperty('display');
    sec.classList.remove('is-collapsed');
  }

  function providedExtensionsFromState() {
    var paths = [
      '_data.defaults.data.provided_extensions',
      'defaults.data.provided_extensions',
      '_data.settings.data.general.provided_extensions',
      'settings.data.general.provided_extensions',
      '_data.defaults.data.provided_extensions',
      'defaults.data.provided_extensions',
      '_data.settings.data.general.provided_extensions',
      'settings.data.general.provided_extensions'
    ];
    var i, v, extracted, normalized;
    for (i = 0; i < paths.length; i++) {
      v = g7Get(paths[i]);
      if (v == null || v === '') continue;
      if (typeof v === 'object' && !Array.isArray(v)) {
        extracted = extractExtTokensFromObject(v);
        if (!extracted.length) continue;
        normalized = normalizeExtList(extracted);
      } else {
        normalized = normalizeExtList(v);
      }
      if (normalized && normalized.length) {
        return normalized;
      }
    }
    return EXT_FALLBACK.slice();
  }

  function extFieldKey(ext) {
    return 'ext_' + String(ext || '').toLowerCase();
  }

  function syncProvidedExtOptions() {
    var host = document.querySelector('#ext_row, [data-cmb-ext-row]');
    if (!host) return;
    var allowed = providedExtensionsFromState();
    var allowedSet = {};
    allowed.forEach(function (e) { allowedSet[e] = 1; });
    EXT_KEYS = allowed.map(extFieldKey);
    var existing = {};
    host.querySelectorAll('label, [data-cmb-ext-wrap]').forEach(function (wrap) {
      var input = wrap.querySelector('input[type="checkbox"], input[name^="ext_"]');
      var name = input && input.getAttribute('name');
      var label = (wrap.textContent || '').trim().toUpperCase();
      var ext = name && name.indexOf('ext_') === 0 ? name.slice(4).toUpperCase() : label.split(/\s+/)[0];
      if (!ext) return;
      existing[ext] = wrap;
      if (allowedSet[ext]) {
        wrap.style.removeProperty('display');
        wrap.removeAttribute('hidden');
      } else {
        wrap.style.display = 'none';
        wrap.setAttribute('hidden', 'hidden');
        if (input) {
          try { input.checked = false; } catch (e) {}
        }
      }
    });
    allowed.forEach(function (ext) {
      if (existing[ext]) return;
      var key = extFieldKey(ext);
      var label = document.createElement('label');
      label.className = 'cmb-order-check-label inline-flex items-center gap-2 text-sm text-gray-800 dark:text-gray-100 cursor-pointer';
      label.setAttribute('data-cmb-ext-wrap', ext);
      var input = document.createElement('input');
      input.type = 'checkbox';
      input.className = 'cmb-order-check';
      input.name = key;
      var span = document.createElement('span');
      span.className = 'cmb-order-check-text';
      span.textContent = ext;
      label.appendChild(input);
      label.appendChild(span);
      host.appendChild(label);
    });
  }

  function bindCondToggles() {
    syncCond('rush_fee_enabled', '.cmb-cond-rush');
    syncCond('revision_enabled', '.cmb-cond-rev');
    var names = ['rush_fee_enabled', 'revision_enabled'];
    var i;
    for (i = 0; i < names.length; i++) {
      (function (nm) {
        var box = findCheck(nm);
        if (!box || box.getAttribute('data-cmb-cond-bound')) {
          return;
        }
        box.setAttribute('data-cmb-cond-bound', '1');
        var onToggle = function () {
          setTimeout(function () {
            syncCond('rush_fee_enabled', '.cmb-cond-rush');
            syncCond('revision_enabled', '.cmb-cond-rev');
          }, 0);
        };
        box.addEventListener('change', onToggle, true);
        box.addEventListener('click', onToggle, true);
        var host = box.closest ? box.closest('label') : null;
        if (host) {
          host.addEventListener('click', onToggle, true);
        }
      })(names[i]);
    }
  }

  var applyingProfileName = false;

  function profileNameBox() {
    return document.querySelector('[data-cmb-profile-name]');
  }

  function profileNameSeed() {
    var box = profileNameBox();
    var fromBox = box && box.getAttribute('data-cmb-profile-name-seed');
    if (fromBox && String(fromBox).trim()) {
      return String(fromBox).trim();
    }
    var seed = document.querySelector('[data-cmb-profile-name-seed]');
    if (seed) {
      var attr = seed.getAttribute('data-cmb-profile-name-seed');
      if (attr && String(attr).trim()) {
        return String(attr).trim();
      }
    }
    return '';
  }

  function applyProfileName() {
    var seed = profileNameSeed();
    if (!seed) {
      return;
    }
    applyingProfileName = true;
    fillNamed('contact_name', seed);
    setLocal({ 'form.contact_name': seed });
    applyingProfileName = false;
  }

  function uncheckProfileName() {
    var box = profileNameBox();
    if (!box || !box.checked) {
      return;
    }
    box.checked = false;
    try {
      box.dispatchEvent(new Event('input', { bubbles: true }));
      box.dispatchEvent(new Event('change', { bubbles: true }));
    } catch (e) {}
  }

  function bindProfileName() {
    var box = profileNameBox();
    var input = document.querySelector('[name="contact_name"]');
    if (!box || !input) {
      return;
    }
    if (!box.getAttribute('data-cmb-bound')) {
      box.setAttribute('data-cmb-bound', '1');
      box.addEventListener('change', function () {
        if (box.checked) {
          applyProfileName();
        }
      });
    }
    if (!input.getAttribute('data-cmb-profile-bound')) {
      input.setAttribute('data-cmb-profile-bound', '1');
      var onManual = function () {
        if (applyingProfileName) {
          return;
        }
        if (String(input.value || '').trim() !== profileNameSeed()) {
          uncheckProfileName();
        }
      };
      input.addEventListener('input', onManual);
      input.addEventListener('change', onManual);
    }
    if (profileNameSeed() && String(input.value || '').trim() === profileNameSeed()) {
      box.checked = true;
    }
  }

  function readNamedValue(name) {
    var el = document.querySelector('[name="' + name + '"]');
    if (!el) {
      return '';
    }
    if (el.value != null && el.tagName !== 'DIV' && el.tagName !== 'BUTTON') {
      return String(el.value || '');
    }
    var sel = el.querySelector && el.querySelector('select');
    if (sel && sel.value) {
      return String(sel.value);
    }
    try {
      if (window.G7Core && window.G7Core.state && typeof window.G7Core.state.get === 'function') {
        return String(window.G7Core.state.get('_local.form.' + name) || window.G7Core.state.get('form.' + name) || '');
      }
    } catch (e) {}
    return '';
  }

  function bindTypeSelect() {
    var typeHost = document.querySelector('[name="type"]');
    if (typeHost && !typeHost.getAttribute('data-cmb-bound')) {
      typeHost.setAttribute('data-cmb-bound', '1');
      typeHost.addEventListener(
        'change',
        function (e) {
          var v = asTypeSlug((e && e.target && e.target.value) || readNamedValue('type') || g7Get('_local.form.type'));
          if (v) {
            setLocal({ 'form.type': v });
          }
          loadTypes(function () {
            applyTypeFlags(v);
          });
        },
        true
      );
    }
    var current = asTypeSlug(readNamedValue('type') || g7Get('_local.form.type'));
    if (current) {
      setLocal({ 'form.type': current });
      loadTypes(function () {
        applyTypeFlags(current);
      });
    }
    if (!document.documentElement.getAttribute('data-cmb-type-opt-bound')) {
      document.documentElement.setAttribute('data-cmb-type-opt-bound', '1');
      document.addEventListener(
        'click',
        function (e) {
          var opt = e.target && e.target.closest ? e.target.closest('[role="option"]') : null;
          if (!opt) {
            return;
          }
          var label = String(opt.textContent || '').replace(/\s+/g, ' ').trim();
          loadTypes(function () {
            var list = catalogTypes();
            var i;
            var row;
            var slug = '';
            for (i = 0; i < list.length; i++) {
              row = list[i];
              if (row.label === label || row.name === label || String(row.value) === label || String(row.slug) === label || (row.label && label.indexOf(row.label) !== -1)) {
                slug = asTypeSlug(row.value || row.slug);
                break;
              }
            }
            if (slug) {
              setLocal({ 'form.type': slug });
              paintSelectTrigger('type', slug, optionLabelFor('type', slug));
              applyTypeFlags(slug);
            }
          });
        },
        true
      );
    }
  }

  var SIZE_MAX = 20;
  var sizesTouched = false;
  var lastSizeSeed = '';

  function emptySizeRow() {
    return { name: '', w: '', d: '', h: '' };
  }

  function sizeFieldClass() {
    return 'cmb-order-field rounded-lg border border-gray-300 dark:border-gray-600 bg-background dark:bg-gray-900 px-3 py-2.5 text-sm text-gray-900 dark:text-white';
  }

  function parseSizes(raw) {
    if (Array.isArray(raw)) {
      return raw.map(normalizeSizeRowJs);
    }
    if (typeof raw === 'string' && raw.trim() !== '') {
      try {
        var decoded = JSON.parse(raw);
        if (Array.isArray(decoded)) {
          return decoded.map(normalizeSizeRowJs);
        }
      } catch (e) {}
    }
    return [];
  }

  function normalizeSizeRowJs(item) {
    if (!item || typeof item !== 'object') {
      return emptySizeRow();
    }
    return {
      name: String(item.name || ''),
      w: item.w != null && item.w !== '' ? String(item.w) : '',
      d: item.d != null && item.d !== '' ? String(item.d) : '',
      h: item.h != null && item.h !== '' ? String(item.h) : ''
    };
  }

  function serializeSizes(rows) {
    var out = [];
    var i;
    var row;
    for (i = 0; i < rows.length && i < SIZE_MAX; i++) {
      row = rows[i] || emptySizeRow();
      out.push({
        name: String(row.name || '').trim(),
        w: row.w === '' || row.w == null ? null : Number(row.w),
        d: row.d === '' || row.d == null ? null : Number(row.d),
        h: row.h === '' || row.h == null ? null : Number(row.h)
      });
    }
    return out;
  }

  function readSizeSeed() {
    var seedEl = document.querySelector('[data-cmb-sizes-seed]');
    var fromAttr = parseSizes(seedEl && seedEl.getAttribute('data-cmb-sizes-seed'));
    if (fromAttr.length) {
      return fromAttr;
    }
    var hidden = document.querySelector('[name="sizes"]');
    var fromHidden = parseSizes(hidden && hidden.value);
    if (fromHidden.length) {
      return fromHidden;
    }
    var w = document.querySelector('[name="size_w"]');
    var d = document.querySelector('[name="size_d"]');
    var h = document.querySelector('[name="size_h"]');
    if (w && d && h && (String(w.value || '') || String(d.value || '') || String(h.value || ''))) {
      return [{ name: '', w: w.value || '', d: d.value || '', h: h.value || '' }];
    }
    return [emptySizeRow()];
  }

  function rowsFromDom(list) {
    var nodes = list.querySelectorAll('[data-cmb-size-row]');
    var rows = [];
    var i;
    var node;
    for (i = 0; i < nodes.length; i++) {
      node = nodes[i];
      rows.push({
        name: (node.querySelector('[data-cmb-size-name]') || {}).value || '',
        w: (node.querySelector('[data-cmb-size-w]') || {}).value || '',
        d: (node.querySelector('[data-cmb-size-d]') || {}).value || '',
        h: (node.querySelector('[data-cmb-size-h]') || {}).value || ''
      });
    }
    return rows;
  }

  function buildSizeRow(row, canRemove) {
    var wrap = document.createElement('div');
    wrap.setAttribute('data-cmb-size-row', '1');
    wrap.className = 'cmb-size-row flex flex-wrap items-center gap-2';
    wrap.innerHTML =
      '<input data-cmb-size-name type="text" maxlength="80" placeholder="이름" class="' +
      sizeFieldClass() +
      ' cmb-size-name">' +
      '<input data-cmb-size-w type="number" min="0" placeholder="W" class="' +
      sizeFieldClass() +
      ' cmb-size-dim">' +
      '<span class="text-gray-400">×</span>' +
      '<input data-cmb-size-d type="number" min="0" placeholder="D" class="' +
      sizeFieldClass() +
      ' cmb-size-dim">' +
      '<span class="text-gray-400">×</span>' +
      '<input data-cmb-size-h type="number" min="0" placeholder="H" class="' +
      sizeFieldClass() +
      ' cmb-size-dim">' +
      '<button type="button" data-cmb-size-remove class="cmb-size-remove shrink-0 px-2.5 py-2 text-sm rounded-lg border border-gray-300 dark:border-gray-600 text-gray-800 dark:text-gray-200">삭제</button>';
    wrap.querySelector('[data-cmb-size-name]').value = row.name || '';
    wrap.querySelector('[data-cmb-size-w]').value = row.w || '';
    wrap.querySelector('[data-cmb-size-d]').value = row.d || '';
    wrap.querySelector('[data-cmb-size-h]').value = row.h || '';
    var del = wrap.querySelector('[data-cmb-size-remove]');
    if (!canRemove) {
      del.hidden = true;
    }
    return wrap;
  }

  function renderSizeRows(list, rows) {
    if (!rows.length) {
      rows = [emptySizeRow()];
    }
    if (rows.length > SIZE_MAX) {
      rows = rows.slice(0, SIZE_MAX);
    }
    list.innerHTML = '';
    var i;
    for (i = 0; i < rows.length; i++) {
      list.appendChild(buildSizeRow(rows[i], rows.length > 1));
    }
    var add = document.querySelector('[data-cmb-size-add]');
    if (add) {
      add.disabled = rows.length >= SIZE_MAX;
    }
  }

  function syncSizes(list) {
    var rows = rowsFromDom(list);
    if (!rows.length) {
      rows = [emptySizeRow()];
    }
    var first = rows[0];
    var json = JSON.stringify(serializeSizes(rows));
    fillNamed('sizes', json);
    fillNamed('size_w', first.w || '');
    fillNamed('size_d', first.d || '');
    fillNamed('size_h', first.h || '');
    setLocal({
      'form.sizes': json,
      'form.size_w': first.w || '',
      'form.size_d': first.d || '',
      'form.size_h': first.h || ''
    });
    lastSizeSeed = json;
  }

  function fillSizeRows(rows) {
    var list = document.querySelector('[data-cmb-sizes-list]');
    if (!list) {
      return;
    }
    sizesTouched = true;
    renderSizeRows(list, rows);
    syncSizes(list);
  }


  function randomSizeRow() {
    var names = ['본체', '뚜껑', '브라켓', '하우징', '베이스', '커버', '프레임', '조인트'];
    var name = names[Math.floor(Math.random() * names.length)];
    var w = 20 + Math.floor(Math.random() * 280);
    var d = 20 + Math.floor(Math.random() * 200);
    var h = 5 + Math.floor(Math.random() * 120);
    return { name: name, w: w, d: d, h: h };
  }

  function ensureRandomSizeButton(root, list) {
    root = root || document.querySelector('[data-cmb-sizes]');
    list = list || document.querySelector('[data-cmb-sizes-list]');
    if (!root || !list) return;
    if (root.querySelector('[data-cmb-size-random]')) return;
    var add = root.querySelector('[data-cmb-size-add]');
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.setAttribute('data-cmb-size-random', '1');
    btn.textContent = '랜덤 추가';
    btn.className = 'cmb-size-random inline-flex items-center px-3 py-1.5 text-sm rounded-lg border border-dashed border-amber-500/70 text-amber-700 dark:text-amber-300 ml-2';
    btn.title = '무작위 제작 사양 행 추가';
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      var rows = rowsFromDom(list);
      if (rows.length >= SIZE_MAX) return;
      rows.push(randomSizeRow());
      sizesTouched = true;
      renderSizeRows(list, rows);
      syncSizes(list);
    });
    if (add && add.parentNode) {
      add.parentNode.insertBefore(btn, add.nextSibling);
    } else {
      root.appendChild(btn);
    }
  }

  /** Reload type/status/audience/ext options from settings/catalog after save+redirect. */
  function reloadFormCatalog() {
    if (!document.querySelector('.cmb-order-card, [data-cmb-job-form]')) return;
    typesCache = null;
    loadTypes(function () {
      var list = catalogTypes().map(normalizeTypeOption).filter(Boolean);
      try {
        dispatch('setState', { target: 'data', 'defaults.data.types': list });
      } catch (e1) {}
      try {
        setLocal({}); // no-op keep
        if (window.G7Core && window.G7Core.state && typeof window.G7Core.state.set === 'function') {
          window.G7Core.state.set('defaults.data.types', list);
        }
      } catch (e2) {}
      // Re-paint current select values so empty triggers recover
      var curType = asTypeSlug(readNamedValue('type') || g7Get('_local.form.type') || '');
      var curStatus = normalizeStatusSlug(readNamedValue('status') || g7Get('_local.form.status') || 'quote_request');
      var curAud = normalizeAudienceSlug(readNamedValue('audience') || g7Get('_local.form.audience') || 'all');
      if (curType) {
        var row = findTypeRow(curType);
        if (row) curType = asTypeSlug(row.value || row.slug) || curType;
        setLocal({ 'form.type': curType, 'form.status': curStatus, 'form.audience': curAud });
        paintSelectTrigger('type', curType, optionLabelFor('type', curType));
        paintSelectTrigger('status', curStatus, optionLabelFor('status', curStatus));
        paintSelectTrigger('audience', curAud, optionLabelFor('audience', curAud));
        applyTypeFlags(curType);
      } else {
        setLocal({ 'form.status': curStatus, 'form.audience': curAud });
        paintSelectTrigger('status', curStatus, optionLabelFor('status', curStatus));
        paintSelectTrigger('audience', curAud, optionLabelFor('audience', curAud));
      }
      syncProvidedExtOptions();
      syncAudienceSection();
    });
    // Also refresh provided_extensions from defaults if API returned them
    fetch('/api/modules/custom-maker_bids/jobs/form-defaults', { credentials: 'include', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (body) {
        var data = body && body.data ? body.data : body;
        if (!data) return;
        if (data.types && data.types.length) {
          typesCache = extractTypesList(data.types).map(normalizeTypeOption).filter(Boolean);
        }
        if (data.provided_extensions) {
          try {
            if (window.G7Core && window.G7Core.state && typeof window.G7Core.state.set === 'function') {
              window.G7Core.state.set('defaults.data.provided_extensions', normalizeExtList(data.provided_extensions));
            }
          } catch (e3) {}
        }
        if (data.upload_token && !g7Get('_local.form.upload_token')) {
          setLocal({ 'form.upload_token': data.upload_token });
        }
        syncProvidedExtOptions();
      })
      .catch(function () {});
  }

  function bindSizes() {
    var root = document.querySelector('[data-cmb-sizes]');
    var list = document.querySelector('[data-cmb-sizes-list]');
    var add = document.querySelector('[data-cmb-size-add]');
    if (!root || !list) {
      return;
    }
    if (!root.getAttribute('data-cmb-bound')) {
      root.setAttribute('data-cmb-bound', '1');
      list.addEventListener('input', function () {
        sizesTouched = true;
        syncSizes(list);
      });
      list.addEventListener('change', function () {
        sizesTouched = true;
        syncSizes(list);
      });
      list.addEventListener('click', function (e) {
        var btn = e.target.closest ? e.target.closest('[data-cmb-size-remove]') : null;
        if (!btn) {
          return;
        }
        e.preventDefault();
        e.stopPropagation();
        var rows = rowsFromDom(list);
        if (rows.length <= 1) {
          return;
        }
        var rowEl = btn.closest('[data-cmb-size-row]');
        var nodes = list.querySelectorAll('[data-cmb-size-row]');
        var idx = Array.prototype.indexOf.call(nodes, rowEl);
        if (idx < 0) {
          return;
        }
        rows.splice(idx, 1);
        sizesTouched = true;
        renderSizeRows(list, rows);
        syncSizes(list);
      });
      if (add) {
        add.addEventListener('click', function (e) {
          e.preventDefault();
          e.stopPropagation();
          var rows = rowsFromDom(list);
          if (rows.length >= SIZE_MAX) {
            return;
          }
          rows.push(emptySizeRow());
          sizesTouched = true;
          renderSizeRows(list, rows);
          syncSizes(list);
        });
      }
      ensureRandomSizeButton(root, list);
    }
    ensureRandomSizeButton(root, list);
    if (!sizesTouched) {
      var seed = readSizeSeed();
      var seedJson = JSON.stringify(serializeSizes(seed));
      if (!list.querySelector('[data-cmb-size-row]') || seedJson !== lastSizeSeed) {
        renderSizeRows(list, seed);
        lastSizeSeed = seedJson;
      }
    }
  }

  var applyingProfileFill = false;
  var PROFILE_FILL_FIELDS = ['name', 'manager_name', 'phone', 'email', 'zipcode', 'address', 'address_detail'];

  function profileFillBox() {
    return document.querySelector('[data-cmb-profile-fill]');
  }

  function profileFillSeed(field) {
    var box = profileFillBox();
    if (!box) {
      return '';
    }
    var attr = box.getAttribute('data-cmb-seed-' + field);
    return attr ? String(attr).trim() : '';
  }

  function applyProfileFill() {
    var i;
    var field;
    var seed;
    var map = {};
    var any = false;
    applyingProfileFill = true;
    for (i = 0; i < PROFILE_FILL_FIELDS.length; i++) {
      field = PROFILE_FILL_FIELDS[i];
      seed = profileFillSeed(field);
      if (!seed) {
        continue;
      }
      any = true;
      fillNamed(field, seed);
      map['company.' + field] = seed;
    }
    if (any) {
      setLocal(map);
    }
    applyingProfileFill = false;
  }

  function bindProfileFill() {
    var box = profileFillBox();
    if (!box) {
      return;
    }
    if (!box.getAttribute('data-cmb-bound')) {
      box.setAttribute('data-cmb-bound', '1');
      box.addEventListener('change', function () {
        if (box.checked) {
          applyProfileFill();
        }
      });
    }
    var i;
    var field;
    var input;
    for (i = 0; i < PROFILE_FILL_FIELDS.length; i++) {
      field = PROFILE_FILL_FIELDS[i];
      input = document.querySelector('[name="' + field + '"]');
      if (!input || input.getAttribute('data-cmb-profile-fill-bound')) {
        continue;
      }
      input.setAttribute('data-cmb-profile-fill-bound', '1');
      (function (f, el) {
        var onManual = function () {
          if (applyingProfileFill) {
            return;
          }
          if (String(el.value || '').trim() !== profileFillSeed(f) && box.checked) {
            box.checked = false;
            try {
              box.dispatchEvent(new Event('input', { bubbles: true }));
              box.dispatchEvent(new Event('change', { bubbles: true }));
            } catch (e) {}
          }
        };
        el.addEventListener('input', onManual);
        el.addEventListener('change', onManual);
      })(field, input);
    }
  }

  function syncCompanyJobTypes() {
    var boxes = document.querySelectorAll('[data-cmb-job-type]');
    var slugs = [];
    var i;
    for (i = 0; i < boxes.length; i++) {
      if (boxes[i].checked) {
        slugs.push(boxes[i].getAttribute('data-cmb-job-type'));
      }
    }
    fillNamed('job_types', JSON.stringify(slugs));
    setLocal({ 'company.job_types': slugs });
  }

  function renderCompanyJobTypes(host) {
    var list = (typesCache && typesCache.length) ? typesCache : TYPE_FALLBACK;
    var i;
    var row;
    var slug;
    var label;
    var box;
    var span;
    host.innerHTML = '';
    host.setAttribute('data-cmb-job-types-ready', '1');
    for (i = 0; i < list.length; i++) {
      row = list[i];
      slug = row.value || row.slug;
      label = document.createElement('label');
      label.className = 'cmb-order-check-label inline-flex items-center gap-2 text-sm cursor-pointer';
      box = document.createElement('input');
      box.type = 'checkbox';
      box.className = 'cmb-order-check';
      box.setAttribute('data-cmb-job-type', slug);
      box.name = 'job_type_' + slug;
      span = document.createElement('span');
      span.className = 'cmb-order-check-text';
      span.textContent = row.label || row.name || slug;
      label.appendChild(box);
      label.appendChild(span);
      host.appendChild(label);
      box.addEventListener('change', syncCompanyJobTypes);
    }
  }

  function bindCompanyJobTypes() {
    var host = document.querySelector('[data-cmb-job-types]');
    if (!host) {
      return;
    }
    if (host.getAttribute('data-cmb-job-types-ready')) {
      return;
    }
    loadTypes(function () {
      renderCompanyJobTypes(host);
    });
  }

  function bindLogoLimit() {
    var host = document.querySelector('[data-cmb-logo-uploader], #cmb_logo_uploader');
    if (!host || host.getAttribute('data-cmb-logo-bound')) {
      return;
    }
    host.setAttribute('data-cmb-logo-bound', '1');
    host.addEventListener('change', function (e) {
      var input = e.target;
      if (!input || input.type !== 'file' || !input.files || !input.files[0]) {
        return;
      }
      var file = input.files[0];
      if (file.type && file.type.indexOf('image/') !== 0) {
        dispatch('toast', { type: 'error', message: '이미지 파일만 올릴 수 있습니다.' });
        input.value = '';
        return;
      }
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () {
        URL.revokeObjectURL(url);
        if (img.width > 512 || img.height > 512) {
          dispatch('toast', { type: 'error', message: '로고는 최대 512×512 픽셀입니다.' });
          input.value = '';
        }
      };
      img.onerror = function () {
        URL.revokeObjectURL(url);
      };
      img.src = url;
    }, true);
  }


  function fillCheckboxNamed(name, on) {
    var nodes = document.querySelectorAll('[name="' + name + '"]');
    var i;
    var el;
    for (i = 0; i < nodes.length; i++) {
      el = nodes[i];
      if (el.type !== 'checkbox') continue;
      el.checked = !!on;
      try {
        el.dispatchEvent(new Event('input', { bubbles: true }));
        el.dispatchEvent(new Event('change', { bubbles: true }));
      } catch (e) {}
    }
  }

  function applyJobDummyFill() {
    // modeling type so 제공 확장자 section opens (includes_modeling)
    var typeSlug = 'modeling_3d';
    var typeLabel = '3D 모델링';
    var closes = '';
    try {
      var d = new Date();
      d.setDate(d.getDate() + 14);
      closes = d.toISOString().slice(0, 16);
    } catch (e) {
      closes = '2026-10-01T18:00';
    }
    var sizesJson = JSON.stringify([
      { name: '본체', w: 120, d: 80, h: 45 },
      { name: '뚜껑', w: 120, d: 80, h: 8 }
    ]);
    syncProvidedExtOptions();
    var allowedExts = providedExtensionsFromState();
    var map = {
      'form.title': '[테스트] 아크릴 하우징 소량 출력 의뢰',
      'form.type': typeSlug,
      'form.status': 'quote_request',
      'form.audience': 'all',
      'form.budget_min': '80000',
      'form.budget_max': '150000',
      'form.closes_at': closes,
      'form.rush_fee_enabled': false,
      'form.schedule_premium_enabled': false,
      'form.revision_enabled': true,
      'form.revision_count': '2',
      'form.revision_cost': '15000',
      'form.ownership_requested': false,
      'form.description': '테스트용 더미 데이터입니다. PLA 흑색, 지지대 최소화, 후가공 샌딩 요청. 수량 3개.',
      'form.size_w': '120',
      'form.size_d': '80',
      'form.size_h': '45',
      'form.sizes': sizesJson,
      'form.contact_name': '김테스트',
      'form.contact_phone': '010-1234-5678',
      'form.contact_email': 'test.maker@example.com',
      'form.contact_hours_from': '09:00',
      'form.contact_hours_to': '18:00',
      'form.zipcode': '06236',
      'form.address': '서울특별시 강남구 테헤란로 123',
      'form.address_detail': '테스트타워 10층 1001호',
      'form.manager_name': '이담당',
      'form.manager_phone': '010-9876-5432',
      'form.manager_email': 'manager@example.com',
      'form.requires_address': '0',
      'form.includes_modeling': '1',
      'form.terms_agreed': true
    };
    var pick = ['STL', 'OBJ', '3MF', 'PDF'];
    allowedExts.forEach(function (ext) {
      var on = pick.indexOf(String(ext).toUpperCase()) !== -1;
      map['form.' + extFieldKey(ext)] = on;
    });
    map['form.provided_extensions'] = pick.filter(function (e) {
      return allowedExts.indexOf(e) !== -1 || allowedExts.indexOf(e.toLowerCase()) !== -1;
    });
    if (!map['form.provided_extensions'].length) {
      map['form.provided_extensions'] = allowedExts.slice(0, 3);
    }
    setLocal(map);
    Object.keys(map).forEach(function (k) {
      var name = k.replace(/^form\./, '');
      var val = map[k];
      if (typeof val === 'boolean') {
        fillCheckboxNamed(name, val);
      } else if (name !== 'provided_extensions') {
        fillNamed(name, String(val));
      }
    });
    // Selects: set value + trigger change so dependent fields refresh
    function afterType() {
      applyTypeFlags(typeSlug);
      syncExtVisibility(true);
      syncProvidedExtOptions();
      allowedExts.forEach(function (ext) {
        var on = map['form.' + extFieldKey(ext)];
        fillCheckboxNamed(extFieldKey(ext), !!on);
      });
      fillSizeRows([
        { name: '본체', w: 120, d: 80, h: 45 },
        { name: '뚜껑', w: 120, d: 80, h: 8 }
      ]);
      try {
        dispatch('toast', { type: 'success', message: '더미 입력(테스트) — 제출되지 않았습니다. 값을 확인하세요.' });
      } catch (e3) {}
    }
    loadTypes(function () {
      setG7Select('type', typeSlug, function () {
        setG7Select('status', 'quote_request', function () {
          setG7Select('audience', 'all', function () {
            afterType();
          });
        });
      });
    });
  }

  function applyDummyJobForm() {
    applyJobDummyFill();
  }

  function applyCompanyDummyFill() {
    var map = {
      'company.kind': 'company',
      'company.name': '[테스트] 메이커랩 코리아',
      'company.business_no': '123-45-67890',
      'company.bio': '테스트용 더미 업체 소개입니다. 3D 출력·후가공 전문.',
      'company.note': '테스트용 더미 업체 소개입니다. 3D 출력·후가공 전문.',
      'company.homepage_url': 'https://example.com',
      'company.portfolio_url': 'https://example.com/portfolio',
      'company.manager_name': '박매니저',
      'company.phone': '02-1234-5678',
      'company.email': 'company.test@example.com',
      'company.zipcode': '04147',
      'company.address': '서울특별시 마포구 월드컵북로 396',
      'company.address_detail': '누리꿈스퀘어 비즈니스타워 5층',
      'company.job_type_print_3d': true,
      'company.job_type_modeling_3d': true
    };
    setLocal(map);
    fillNamed('name', map['company.name']);
    fillNamed('business_no', map['company.business_no']);
    fillNamed('bio', map['company.bio']);
    fillNamed('homepage_url', map['company.homepage_url']);
    fillNamed('portfolio_url', map['company.portfolio_url']);
    fillNamed('manager_name', map['company.manager_name']);
    fillNamed('phone', map['company.phone']);
    fillNamed('email', map['company.email']);
    fillNamed('zipcode', map['company.zipcode']);
    fillNamed('address', map['company.address']);
    fillNamed('address_detail', map['company.address_detail']);
    fillCheckboxNamed('job_type_print_3d', true);
    fillCheckboxNamed('job_type_modeling_3d', true);
    if (typeof setG7Select === 'function') {
      setG7Select('kind', 'company');
    } else {
      paintSelectTrigger('kind', 'company', '업체');
    }
    try {
      dispatch('toast', { type: 'success', message: '더미 입력(테스트) — 제출되지 않았습니다. 값을 확인하세요.' });
    } catch (e) {}
  }

  function ensureDummyFillButton(root, kind) {
    if (!root) return;
    if (root.querySelector('[data-cmb-dummy-fill="' + kind + '"]')) return;
    var anchor =
      root.querySelector('.cmb-order-submit') ||
      root.querySelector('[data-cmb-company-submit]') ||
      root.querySelector('button');
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.setAttribute('data-cmb-dummy-fill', kind);
    btn.textContent = '더미 입력 (테스트)';
    btn.className =
      'cmb-dummy-fill w-full mb-2 px-4 py-2 rounded-lg border border-dashed border-amber-500/70 text-amber-700 dark:text-amber-300 text-sm font-medium bg-amber-50/40 dark:bg-amber-950/30';
    btn.title = '테스트 채우기 — 서버로 제출하지 않습니다';
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (kind === 'company') applyCompanyDummyFill();
      else applyJobDummyFill();
    });
    if (anchor && anchor.parentNode) {
      anchor.parentNode.insertBefore(btn, anchor);
    } else {
      root.appendChild(btn);
    }
  }

  function bindDummyFill() {
    document.querySelectorAll('[data-cmb-dummy-fill]').forEach(function (btn) {
      if (btn.getAttribute('data-cmb-dummy-bound')) return;
      btn.setAttribute('data-cmb-dummy-bound', '1');
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var kind = btn.getAttribute('data-cmb-dummy-fill') || 'job';
        if (kind === 'company') applyCompanyDummyFill();
        else applyJobDummyFill();
      });
    });
    ensureDummyFillButton(document.querySelector('.cmb-order-card'), 'job');
    ensureDummyFillButton(document.querySelector('[data-cmb-company-form], .cmb-company-form'), 'company');
  }

  function bind() {
    ensureThemeCss();
    var btn = document.querySelector('[data-cmb-postcode]');
    if (btn && !btn.getAttribute('data-cmb-bound')) {
      btn.setAttribute('data-cmb-bound', '1');
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        openPostcode();
      });
    }
    bindDaytime();
    bindCondToggles();
    syncAudienceSection();
    syncProvidedExtOptions();
    bindProfileName();
    bindProfileFill();
    bindTypeSelect();
    bindSizes();
    bindFieldSync();
    bindCompanySubmit();
    bindHarvest();
    syncCompanySubmit();
    bindCompanyJobTypes();
    bindLogoLimit();
    bindDummyFill();
    reloadFormCatalog();
  }

  bind();
  document.addEventListener('DOMContentLoaded', bind);
  setTimeout(bind, 300);
  setTimeout(bind, 1000);
  setTimeout(bind, 2500);
  setTimeout(function () { syncAudienceSection(); syncProvidedExtOptions(); reloadFormCatalog(); }, 400);
  setTimeout(function () { syncAudienceSection(); syncProvidedExtOptions(); reloadFormCatalog(); }, 1200);
  setTimeout(function () { syncAudienceSection(); syncProvidedExtOptions(); reloadFormCatalog(); }, 2500);
  // Expose for page.js / debugging
  try {
    window.cmbForm = window.cmbForm || {};
    window.cmbForm.collectCreateJobPayload = collectCreateJobPayload;
    window.cmbForm.applyDummyJobForm = applyDummyJobForm;
    window.cmbForm.syncProvidedExtOptions = syncProvidedExtOptions;
    window.cmbForm.reloadFormCatalog = reloadFormCatalog;
    window.cmbForm.normalizeStatusSlug = normalizeStatusSlug;
  } catch (eExp) {}
})();

