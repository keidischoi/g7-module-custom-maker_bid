(function () {
  var DAUM_SRC = 'https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js';
  var TYPES_URL = '/api/modules/custom-maker_bid/job-types';
  var FORM_CSS = '/api/modules/custom-maker_bid/assets/form.css?v=0.5.3';
  var DAY_FROM = '09:00';
  var DAY_TO = '17:00';
  var EXT_KEYS = ['ext_stl', 'ext_3mf', 'ext_obj', 'ext_step', 'ext_stp', 'ext_gcode', 'ext_fbx'];
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

  function fillNamed(name, value) {
    var nodes = document.querySelectorAll('[name="' + name + '"]');
    var i;
    for (i = 0; i < nodes.length; i++) {
      nodes[i].value = value;
      try {
        nodes[i].dispatchEvent(new Event('input', { bubbles: true }));
        nodes[i].dispatchEvent(new Event('change', { bubbles: true }));
      } catch (e) {}
    }
  }

  function fillCheck(name, checked) {
    var nodes = document.querySelectorAll('[name="' + name + '"]');
    var i;
    for (i = 0; i < nodes.length; i++) {
      nodes[i].checked = !!checked;
      try {
        nodes[i].dispatchEvent(new Event('input', { bubbles: true }));
        nodes[i].dispatchEvent(new Event('change', { bubbles: true }));
      } catch (e) {}
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
          setLocal({
            'form.zipcode': zip,
            'form.address': addr
          });
        }
      }).open();
    });
  }

  function findTypeRow(slug) {
    var list = (typesCache && typesCache.length) ? typesCache : TYPE_FALLBACK;
    var i;
    var row;
    for (i = 0; i < list.length; i++) {
      row = list[i];
      if (row.value === slug || row.slug === slug) {
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
    if (typesCache) {
      cb();
      return;
    }
    fetch(TYPES_URL, { headers: { Accept: 'application/json' } })
      .then(function (res) {
        return res.json();
      })
      .then(function (body) {
        typesCache = (body && body.data) || [];
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
            var list = (typesCache && typesCache.length) ? typesCache : TYPE_FALLBACK;
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

  // TEMP: 모듈 완성 후 삭제 예정 — QA 임의입력 (data-cmb-qa-fill)
  // 이미지·파일 FileUploader 는 절대 채우지 않는다.
  function randInt(min, max) {
    return min + Math.floor(Math.random() * (max - min + 1));
  }

  function pick(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
  }

  function futureStamp(days, hour, minute, asDate) {
    var d = new Date();
    d.setDate(d.getDate() + days);
    d.setHours(hour, minute, 0, 0);
    var stamp =
      d.getFullYear() +
      '-' +
      pad2(d.getMonth() + 1) +
      '-' +
      pad2(d.getDate());
    if (asDate) {
      return stamp;
    }
    return stamp + 'T' + pad2(d.getHours()) + ':' + pad2(d.getMinutes());
  }

  function pickQaType() {
    var i;
    var row;
    var list = [];
    if (typesCache && typesCache.length) {
      for (i = 0; i < typesCache.length; i++) {
        row = typesCache[i];
        list.push(row.value || row.slug);
      }
    }
    return pick(list.length ? list : TYPE_FALLBACK.map(function (t) { return t.value; }));
  }

  function fillQaDummy() {
    loadTypes(function () {
      var type = pickQaType();
      var title = pick([
        'PLA 피규어 15cm 출력',
        '워킹 프로토타입 하우징',
        '캐릭터 피규어 모델링',
        '디자인 목업 케이스',
        'PETG 브라켓 소량 출력',
        '풀패키지 피규어 제작'
      ]);
      var min = randInt(2, 8) * 10000;
      var max = min + randInt(2, 10) * 10000;
      var closes = futureStamp(randInt(5, 14), pick([12, 15, 18, 21]), pick([0, 30]), false);
      var status = pick(['hold', 'request', 'quote_request']);
      var rush = Math.random() < 0.5;
      var premium = Math.random() < 0.4;
      var revision = Math.random() < 0.6;
      var daytime = Math.random() < 0.55;
      var hourPair = daytime
        ? [DAY_FROM, DAY_TO]
        : pick([['10:00', '15:00'], ['13:00', '19:00'], ['18:00', '22:00'], ['20:00', '23:00']]);
      var hourFrom = hourPair[0];
      var hourTo = hourPair[1];
      var sizeSamples = [
        { name: '본체', w: String(randInt(80, 300)), d: String(randInt(60, 220)), h: String(randInt(20, 180)) },
        { name: '뚜껑', w: String(randInt(40, 160)), d: String(randInt(40, 140)), h: String(randInt(10, 40)) },
        { name: '받침대', w: String(randInt(50, 200)), d: String(randInt(50, 180)), h: String(randInt(8, 30)) }
      ];
      var sizeRows = sizeSamples.slice(0, randInt(1, 3));
      var rushDate = rush ? futureStamp(randInt(2, 5), pick([10, 12, 15, 18]), pick([0, 30]), false) : '';
      var revCount = revision ? String(randInt(1, 4)) : '';
      var revCost = revision ? String(randInt(1, 5) * 5000) : '';
      var extKeys = EXT_KEYS;
      var extOn = {};
      var i;
      var onCount = 0;
      var modeling = rowIncludesModeling(findTypeRow(type), type);
      for (i = 0; i < extKeys.length; i++) {
        extOn[extKeys[i]] = modeling && Math.random() < 0.4;
        if (extOn[extKeys[i]]) {
          onCount += 1;
        }
      }
      if (modeling && !onCount) {
        extOn.ext_stl = true;
      }
      var person = pick([
        { name: '홍길동', phone: '010-1234-5678', email: 'gildong@example.com' },
        { name: '김민준', phone: '010-2222-3333', email: 'minjun@example.com' },
        { name: '이서연', phone: '010-5555-7777', email: 'seoyeon@example.com' }
      ]);
      var manager = pick([
        { name: '박지훈', phone: '010-8888-1111', email: 'jihun.park@example.com' },
        { name: '최수아', phone: '010-7777-2222', email: 'sua.choi@example.com' },
        { name: '정하늘', phone: '010-3333-4444', email: 'haneul.jung@example.com' }
      ]);
      var addr = pick([
        { zip: '06236', addr: '서울 강남구 테헤란로 123', detail: '3층' },
        { zip: '48058', addr: '부산 해운대구 센텀중앙로 79', detail: '101호' },
        { zip: '34126', addr: '대전 유성구 대학로 99', detail: '연구동 2층' }
      ]);
      var desc = pick([
        '테스트 임의입력. PLA 무광, 수량 1. 색상은 아이보리.',
        '테스트 임의입력. 후가공(샌딩) 포함, 납기 협의.',
        '테스트 임의입력. PETG, 인필 40%, 레이어 0.2mm.'
      ]);
      applyingDaytime = true;
      fillNamed('title', title);
      fillNamed('type', type);
      fillNamed('budget_min', String(min));
      fillNamed('budget_max', String(max));
      fillNamed('closes_at', closes);
      fillNamed('status', status);
      fillNamed('description', desc);
      fillNamed('contact_name', person.name);
      fillNamed('contact_phone', person.phone);
      fillNamed('contact_hours_from', hourFrom);
      fillNamed('contact_hours_to', hourTo);
      fillNamed('contact_email', person.email);
      fillNamed('zipcode', addr.zip);
      fillNamed('address', addr.addr);
      fillNamed('address_detail', addr.detail);
      fillNamed('manager_name', manager.name);
      fillNamed('manager_phone', manager.phone);
      fillNamed('manager_email', manager.email);
      fillSizeRows(sizeRows);
      fillCheck('rush_fee_enabled', rush);
      fillCheck('schedule_premium_enabled', premium);
      fillCheck('revision_enabled', revision);
      for (i = 0; i < extKeys.length; i++) {
        fillCheck(extKeys[i], extOn[extKeys[i]]);
      }
      var localMap = {
        'form.title': title,
        'form.type': type,
        'form.budget_min': String(min),
        'form.budget_max': String(max),
        'form.closes_at': closes,
        'form.status': status,
        'form.description': desc,
        'form.contact_name': person.name,
        'form.contact_phone': person.phone,
        'form.contact_hours_from': hourFrom,
        'form.contact_hours_to': hourTo,
        'form.contact_email': person.email,
        'form.zipcode': addr.zip,
        'form.address': addr.addr,
        'form.address_detail': addr.detail,
        'form.manager_name': manager.name,
        'form.manager_phone': manager.phone,
        'form.manager_email': manager.email,
        'form.rush_fee_enabled': rush,
        'form.schedule_premium_enabled': premium,
        'form.revision_enabled': revision,
        'form.revision_count': revCount,
        'form.revision_cost': revCost,
        'form.rush_deadline': rushDate
      };
      for (i = 0; i < extKeys.length; i++) {
        localMap['form.' + extKeys[i]] = extOn[extKeys[i]];
      }
      setLocal(localMap);
      applyTypeFlags(type);
      applyingDaytime = false;
      var day = daytimeBox();
      if (day) {
        day.checked = daytime;
      }
      setTimeout(function () {
        if (rush) {
          fillNamed('rush_deadline', rushDate);
          fillCheck('rush_fee_enabled', true);
        }
        if (revision) {
          fillNamed('revision_count', revCount);
          fillNamed('revision_cost', revCost);
          fillCheck('revision_enabled', true);
        }
        fillNamed('manager_name', manager.name);
        fillNamed('manager_phone', manager.phone);
        fillNamed('manager_email', manager.email);
        fillSizeRows(sizeRows);
        for (i = 0; i < extKeys.length; i++) {
          fillCheck(extKeys[i], extOn[extKeys[i]]);
        }
        if (day) {
          day.checked = daytime;
        }
      }, 80);
    });
  }

  function bindQaFill() {
    var btn = document.querySelector('[data-cmb-qa-fill]');
    if (btn && !btn.getAttribute('data-cmb-bound')) {
      btn.setAttribute('data-cmb-bound', '1');
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        fillQaDummy();
      });
    }
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
    bindTypeSelect();
    bindSizes();
    bindQaFill();
  }

  bind();
  document.addEventListener('DOMContentLoaded', bind);
  setTimeout(bind, 300);
  setTimeout(bind, 1000);
  setTimeout(bind, 2500);
})();
