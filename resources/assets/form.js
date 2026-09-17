(function () {
  var DAUM_SRC = 'https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js';
  var TYPES_URL = '/api/modules/custom-maker_bids/job-types';
  var FORM_CSS = '/api/modules/custom-maker_bids/assets/form.css?v=0.10.3';
  var DAY_FROM = '09:00';
  var DAY_TO = '17:00';
  var EXT_KEYS = ['ext_stl', 'ext_3mf', 'ext_obj', 'ext_step', 'ext_stp', 'ext_gcode', 'ext_fbx', 'ext_dwg'];
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
      return String(row);
    }
    if (row.value != null && String(row.value).trim() !== '') {
      return String(row.value);
    }
    if (row.slug != null && String(row.slug).trim() !== '') {
      return String(row.slug);
    }
    if (row.id != null && String(row.id).trim() !== '') {
      return String(row.id);
    }
    return '';
  }

  function typeLabelOf(row, fallback) {
    if (row && typeof row === 'object') {
      return String(row.label || row.name || fallback || typeToken(row) || '');
    }
    return String(fallback || row || '');
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
    for (i = 0; i < nodes.length; i++) {
      el = nodes[i];
      name = el.getAttribute('name');
      if (!name || el.type === 'file') {
        continue;
      }
      tag = (el.tagName || '').toUpperCase();
      if (el.type === 'checkbox') {
        map[prefix + '.' + name] = !!el.checked;
        continue;
      }
      if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
        val = el.value;
        // G7 Select wrappers often leave an empty hidden/input — do not clobber local slug.
        if (selectKeys[name] && String(val || '').trim() === '') {
          continue;
        }
        map[prefix + '.' + name] = val;
      }
    }
    // Prefer live select value / G7 state for critical slugs.
    ['type', 'status', 'audience', 'kind'].forEach(function (k) {
      var live = readNamedValue(k);
      if (live && String(live).trim() !== '') {
        map[prefix + '.' + k] = live;
      }
    });
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
      map[localFormKey() + '.' + name] = el.value;
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
        harvestNamedFields();
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
        var st = companyMeStatus();
        var form = companyFormEl();
        var visible = companyFormIsVisible(form);
        if (st === 'approved') {
          e.preventDefault();
          e.stopImmediatePropagation();
          return;
        }
        if (!form || !visible) {
          e.preventDefault();
          e.stopImmediatePropagation();
          revealCompanyForm();
          return;
        }
        if (st === 'pending') {
          e.preventDefault();
          e.stopImmediatePropagation();
          revealCompanyForm();
          return;
        }
        harvestNamedFields();
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
      row = list[i];
      if (!row) {
        continue;
      }
      if (typeof row !== 'object') {
        row = { value: String(row), label: String(row) };
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
    var text = label || optionLabelFor(name, value);
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
      row = findTypeRow(value);
      if (row) {
        return row.label || row.name || value;
      }
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
    var chosen = String(value || '');
    var label = optionLabelFor(name, chosen);
    var map = {};
    var finish = function () {
      paintSelectTrigger(name, chosen, label);
      if (typeof done === 'function') {
        done();
      }
    };
    var writeState = function (next) {
      chosen = String(next || chosen);
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
    var s = String(slug || '');
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
          var v = (e && e.target && e.target.value) || readNamedValue('type');
          loadTypes(function () {
            applyTypeFlags(v);
          });
        },
        true
      );
    }
    var current = readNamedValue('type');
    if (current) {
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
                slug = row.value || row.slug;
                break;
              }
            }
            if (slug) {
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
    }
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
  }

  bind();
  document.addEventListener('DOMContentLoaded', bind);
  setTimeout(bind, 300);
  setTimeout(bind, 1000);
  setTimeout(bind, 2500);
})();

