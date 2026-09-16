(function () {
  var DAUM_SRC = 'https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js';
  var TYPES_URL = '/api/modules/custom-maker_bid/job-types';
  var FORM_CSS = '/api/modules/custom-maker_bid/assets/form.css?v=0.5.2';
  var DAY_FROM = '09:00';
  var DAY_TO = '17:00';
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

  function applyTypeFlags(slug) {
    if (!slug || !typesCache) {
      return;
    }
    var i;
    var row = null;
    for (i = 0; i < typesCache.length; i++) {
      if (typesCache[i].value === slug || typesCache[i].slug === slug) {
        row = typesCache[i];
        break;
      }
    }
    if (!row) {
      return;
    }
    var needs = row.requires_address !== false && row.is_design_only !== true;
    setLocal({
      'form.requires_address': needs ? '1' : '0'
    });
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
        cb();
      })
      .catch(function () {
        typesCache = [];
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
    var fallback = ['print_3d', 'modeling_3d', 'full_package', 'character_figure', 'design_mockup', 'working_prototype'];
    var i;
    var row;
    var list = [];
    if (typesCache && typesCache.length) {
      for (i = 0; i < typesCache.length; i++) {
        row = typesCache[i];
        list.push(row.value || row.slug);
      }
    }
    return pick(list.length ? list : fallback);
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
      var extKeys = ['ext_stl', 'ext_3mf', 'ext_obj', 'ext_step', 'ext_stp', 'ext_gcode', 'ext_fbx'];
      var extOn = {};
      var i;
      var onCount = 0;
      for (i = 0; i < extKeys.length; i++) {
        extOn[extKeys[i]] = Math.random() < 0.4;
        if (extOn[extKeys[i]]) {
          onCount += 1;
        }
      }
      if (!onCount) {
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
    var typeSelect = document.querySelector('[name="type"]');
    if (typeSelect && !typeSelect.getAttribute('data-cmb-bound')) {
      typeSelect.setAttribute('data-cmb-bound', '1');
      typeSelect.addEventListener('change', function () {
        loadTypes(function () {
          applyTypeFlags(typeSelect.value);
        });
      });
      if (typeSelect.value) {
        loadTypes(function () {
          applyTypeFlags(typeSelect.value);
        });
      }
    }
    bindDaytime();
    bindSizes();
    bindQaFill();
  }

  bind();
  document.addEventListener('DOMContentLoaded', bind);
  setTimeout(bind, 300);
  setTimeout(bind, 1000);
  setTimeout(bind, 2500);
})();
