(function () {
  if (window.__cmbAdminJs38) return;
  window.__cmbAdminJs38 = true;
  var CLS = 'cmb-order-field rounded-lg border border-gray-300 dark:border-gray-600 bg-background px-3 py-2.5 text-sm';
  var ACTIVE_SUFFIX = ' · 현재';
  var painting = false;

  function normalizeStatus(st) {
    st = String(st || '').trim();
    if (st === 'open') return 'quote_request';
    return st;
  }

  function statusFromText(text) {
    text = String(text || '');
    if (text.indexOf('거절') >= 0 || /\brejected\b/i.test(text)) return 'rejected';
    if (text.indexOf('보류') >= 0 || /\bhold\b/i.test(text)) return 'hold';
    if (text.indexOf('승인대기') >= 0 || text.indexOf('draft') >= 0) return 'pending';
    if (text.indexOf('견적') >= 0 || text.indexOf('입찰중') >= 0 || /\bopen\b/i.test(text) || /\bquote_request\b/i.test(text)) return 'quote_request';
    if (/\bapproved\b/i.test(text) || text.indexOf('승인') >= 0) return 'approved';
    if (/\bpending\b/i.test(text)) return 'pending';
    if (text.indexOf('의뢰') >= 0 || /\brequest\b/i.test(text)) return 'request';
    return '';
  }

  function inferEntity(root, st) {
    var ent = root && root.getAttribute ? String(root.getAttribute('data-cmb-entity') || '') : '';
    if (ent) return ent;
    if (root && root.classList) {
      if (root.classList.contains('cmb-entity-company')) return 'company';
      if (root.classList.contains('cmb-entity-job')) return 'job';
    }
    var path = String((location && location.pathname) || '');
    if (/\/admin\/maker-bids\/companies/.test(path)) return 'company';
    if (/\/admin\/maker-bids\/jobs/.test(path)) return 'job';
    if (st === 'approved' || st === 'rejected') return 'company';
    if (st === 'quote_request' || st === 'open' || st === 'request' || st === 'awarded' || st === 'done' || st === 'cancelled') return 'job';
    return '';
  }

  function statusFromRoot(root) {
    if (!root) return '';
    var raw = '';
    if (root.getAttribute) raw = root.getAttribute('data-cmb-status') || '';
    raw = normalizeStatus(raw);
    if (raw) return raw;
    var cls = (root.className && String(root.className)) || '';
    var m = cls.match(/(?:^|\s)cmb-status-([a-z0-9_]+)/i);
    if (m) return normalizeStatus(m[1]);
    var meta = root.querySelector && root.querySelector('.cmb-admin-muted, .cmb-admin-meta');
    return statusFromText(meta && meta.textContent);
  }

  function isApproveOn(st) {
    return st === 'quote_request' || st === 'approved' || st === 'open';
  }

  function isHoldOn(st, entity) {
    if (st === 'hold') return true;
    return entity === 'company' && st === 'pending';
  }

  function baseLabel(btn) {
    var t = (btn.textContent || '').replace(/\s+/g, ' ').trim();
    var i = t.indexOf(ACTIVE_SUFFIX);
    if (i >= 0) t = t.slice(0, i).trim();
    t = t.replace(/^✓\s*/, '');
    return t;
  }

  var PALETTE = {
    approve: {
      on: { bg: 'rgb(5, 150, 105)', fg: '#fff', bd: 'rgb(4, 120, 87)' },
      off: { bg: 'transparent', fg: 'rgb(167, 243, 208)', bd: 'rgba(52, 211, 153, 0.55)' }
    },
    hold: {
      on: { bg: 'rgb(217, 119, 6)', fg: '#fff', bd: 'rgb(180, 83, 9)' },
      off: { bg: 'transparent', fg: 'rgb(253, 230, 138)', bd: 'rgba(251, 191, 36, 0.55)' }
    },
    reject: {
      on: { bg: 'rgb(220, 38, 38)', fg: '#fff', bd: 'rgb(185, 28, 28)' },
      off: { bg: 'transparent', fg: 'rgb(254, 202, 202)', bd: 'rgba(252, 165, 165, 0.55)' }
    }
  };

  function applyPalette(el, pal) {
    if (!el || !el.style) return;
    el.style.setProperty('background', pal.bg, 'important');
    el.style.setProperty('background-color', pal.bg, 'important');
    el.style.setProperty('color', pal.fg, 'important');
    el.style.setProperty('border-color', pal.bd, 'important');
    el.style.setProperty('border-style', 'solid', 'important');
    el.style.setProperty('border-width', '1px', 'important');
    if (pal.bg === 'transparent') {
      el.style.setProperty('box-shadow', 'none', 'important');
      el.style.setProperty('font-weight', '500', 'important');
    } else {
      el.style.setProperty('box-shadow', '0 0 0 2px rgba(0,0,0,0.18)', 'important');
      el.style.setProperty('font-weight', '700', 'important');
    }
  }

  function kindFromEl(b) {
    if (!b) return '';
    var kind = b.getAttribute && b.getAttribute('data-cmb-kind');
    if (kind) return kind;
    if (b.classList && b.classList.contains('cmb-btn-approve')) return 'approve';
    if (b.classList && b.classList.contains('cmb-btn-hold')) return 'hold';
    if (b.classList && b.classList.contains('cmb-btn-reject')) return 'reject';
    var t = baseLabel(b);
    if (t === '승인') return 'approve';
    if (t === '보류') return 'hold';
    if (t === '거절') return 'reject';
    return '';
  }

  function paintBtn(btn, on, kind) {
    if (!btn) return;
    var cls = kind === 'approve' ? 'cmb-btn-approve' : kind === 'hold' ? 'cmb-btn-hold' : 'cmb-btn-reject';
    btn.classList.add(cls);
    btn.setAttribute('data-cmb-kind', kind);
    btn.classList.toggle('is-active', !!on);
    if (on) {
      btn.setAttribute('data-cmb-active', '1');
      btn.setAttribute('aria-pressed', 'true');
    } else {
      btn.removeAttribute('data-cmb-active');
      btn.setAttribute('aria-pressed', 'false');
    }
    var pal = (PALETTE[kind] || PALETTE.approve)[on ? 'on' : 'off'];
    applyPalette(btn, pal);
    var wrap = btn.parentElement;
    if (wrap && wrap !== document.body && (wrap.classList.contains(cls) || (wrap.getAttribute && wrap.getAttribute('data-cmb-kind') === kind))) {
      applyPalette(wrap, pal);
    }
    var base = baseLabel(btn);
    if (base && (btn.textContent || '').indexOf(ACTIVE_SUFFIX) >= 0) {
      btn.textContent = base;
    }
  }

  function buttonsIn(root) {
    var out = { approve: [], hold: [], reject: [] };
    if (!root || !root.querySelectorAll) return out;
    root.querySelectorAll('button, [role="button"], .cmb-btn-approve, .cmb-btn-hold, .cmb-btn-reject, [data-cmb-kind]').forEach(function (b) {
      var k = kindFromEl(b);
      if (k && out[k] && out[k].indexOf(b) < 0) out[k].push(b);
    });
    return out;
  }

  function paintGroup(root, st, entity) {
    var btns = buttonsIn(root);
    btns.approve.forEach(function (b) { paintBtn(b, isApproveOn(st), 'approve'); });
    btns.hold.forEach(function (b) { paintBtn(b, isHoldOn(st, entity), 'hold'); });
    btns.reject.forEach(function (b) { paintBtn(b, st === 'rejected', 'reject'); });
  }

  function jobIdFromPath() {
    var m = String((location && location.pathname) || '').match(/\/admin\/maker-bids\/jobs\/(\d+)/);
    if (m) return m[1];
    var bar = document.querySelector('.cmb-admin-toolbar[data-cmb-job-id]');
    if (bar && bar.getAttribute('data-cmb-job-id')) return String(bar.getAttribute('data-cmb-job-id'));
    return '';
  }

  function markEditingRow(id) {
    document.querySelectorAll('.cmb-admin-row').forEach(function (row) {
      var title = row.querySelector('.cmb-admin-row-title, a');
      var on = !!(id && title && String(title.textContent || '').indexOf('#' + id) >= 0);
      row.classList.toggle('is-cmb-editing', on);
      if (on) row.setAttribute('data-cmb-editing', '1');
      else row.removeAttribute('data-cmb-editing');
    });
  }

  function rememberEditId(id) {
    id = id != null ? String(id).trim() : '';
    if (!id || id === '-' || id === 'undefined' || id === 'null') return '';
    window.__cmbEditCompanyId = id;
    var card = document.querySelector('[data-cmb-company-edit]');
    if (card) {
      card.setAttribute('data-cmb-edit-id', id);
      var hid = card.querySelector('input[name="id"]');
      if (!hid) {
        hid = document.createElement('input');
        hid.type = 'hidden';
        hid.name = 'id';
        hid.setAttribute('data-cmb-edit-id-input', '1');
        card.insertBefore(hid, card.firstChild);
      }
      hid.value = id;
    }
    markEditingRow(id);
    return id;
  }

  function companyIdFromEdit() {
    var id = '';
    if (window.__cmbEditCompanyId) id = String(window.__cmbEditCompanyId);
    var card = document.querySelector('[data-cmb-company-edit]');
    if (!id && card) id = String(card.getAttribute('data-cmb-edit-id') || '');
    if (!id && card) {
      var hid = card.querySelector('input[name="id"]');
      if (hid && hid.value) id = String(hid.value);
    }
    if (!id) {
      try {
        var v = g7Get('_local.edit.id');
        if (v) id = String(v);
        if (!id) {
          var ed = g7Get('_local.edit') || {};
          if (ed && ed.id) id = String(ed.id);
        }
      } catch (e) {}
    }
    if (!id) {
      var text = '';
      try { text = (document.body && document.body.innerText) || ''; } catch (e2) {}
      var h = String(text).match(/선택 업체 관리 \(#(\d+)/);
      if (h) id = h[1];
    }
    if (!id) {
      var marked = document.querySelector('.cmb-admin-row.is-cmb-editing, .cmb-admin-row[data-cmb-editing="1"]');
      if (marked) id = idFromRow(marked);
    }
    if (id && id !== '-' && id !== 'undefined' && id !== 'null') {
      if (String(window.__cmbEditCompanyId || '') !== id) window.__cmbEditCompanyId = id;
      return id;
    }
    return '';
  }

  function readAuthToken() {
    var token = '';
    try {
      if (window.G7Core && window.G7Core.api && typeof window.G7Core.api.getToken === 'function') {
        token = window.G7Core.api.getToken();
      }
    } catch (e) {}
    if (!token) {
      try {
        if (window.AuthManager && typeof window.AuthManager.getInstance === 'function') {
          var auth = window.AuthManager.getInstance();
          if (auth && typeof auth.getToken === 'function') token = auth.getToken();
          if (!token && auth && auth.state && auth.state.token) token = auth.state.token;
        }
      } catch (e2) {}
    }
    var keys = ['auth_token', 'g7_token', 'access_token', 'token'];
    var i;
    var v;
    if (!token) {
      for (i = 0; i < keys.length; i++) {
        try { v = localStorage.getItem(keys[i]); } catch (e3) { v = ''; }
        if (v && v !== 'undefined' && v !== 'null') { token = v; break; }
      }
    }
    if (!token) {
      for (i = 0; i < keys.length; i++) {
        try { v = sessionStorage.getItem(keys[i]); } catch (e4) { v = ''; }
        if (v && v !== 'undefined' && v !== 'null') { token = v; break; }
      }
    }
    if (token && typeof token === 'string' && token.indexOf('Bearer ') === 0) token = token.slice(7);
    if (token && token !== 'undefined' && token !== 'null') return String(token);
    return '';
  }

  function csrfHeaderMap() {
    var headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    var token = csrfToken();
    if (token) {
      headers['X-CSRF-TOKEN'] = token;
      headers['X-XSRF-TOKEN'] = token;
    }
    var bearer = readAuthToken();
    if (bearer) headers.Authorization = 'Bearer ' + bearer;
    return headers;
  }

  function postCompany(id, action) {
    if (!id) return;
    var headers = csrfHeaderMap();
    var body = null;
    if (action === 'hold' || action === 'reject') {
      headers['Content-Type'] = 'application/json';
      var edit = g7Get('_local.edit') || {};
      body = JSON.stringify({
        hold_reason: edit.hold_reason || '',
        rejected_reason: edit.rejected_reason || '',
        admin_memo: edit.admin_memo || ''
      });
    }
    fetch('/api/modules/custom-maker_bids/admin/companies/' + id + '/' + action, {
      method: 'POST',
      credentials: 'include',
      headers: headers,
      body: body
    }).then(function () {
      if (window.G7Core && window.G7Core.dispatch) {
        window.G7Core.dispatch({ handler: 'refetchDataSource', params: { dataSourceId: 'companies' } });
      } else {
        location.reload();
      }
    });
  }

  function ensureCompanyEditButtons() {
    if (document.querySelector('.cmb-admin-company-toolbar[data-cmb-co-btns], [data-cmb-company-edit] [data-cmb-co-btns]')) {
      return;
    }
    var save = null;
    document.querySelectorAll('button').forEach(function (b) {
      if ((b.textContent || '').trim() === '선택 업체 저장') save = b;
    });
    if (!save || save.getAttribute('data-cmb-co-btns')) return;
    save.setAttribute('data-cmb-co-btns', '1');
    var wrap = document.createElement('div');
    wrap.className = 'cmb-admin-toolbar cmb-admin-company-toolbar';
    wrap.setAttribute('data-cmb-co-btns', '1');
    wrap.setAttribute('data-cmb-entity', 'company');
    var parent = save.parentElement || save;
    parent.insertBefore(wrap, save);
    wrap.appendChild(save);
    function add(label, action, confirmMsg) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = label;
      var kind = label === '승인' ? 'cmb-btn-approve' : label === '보류' ? 'cmb-btn-hold' : label === '거절' ? 'cmb-btn-reject' : '';
      b.className = (kind ? kind + ' ' : '') + 'px-3 py-1.5 text-sm rounded-lg border';
      b.setAttribute('data-cmb-kind', label === '승인' ? 'approve' : label === '보류' ? 'hold' : 'reject');
      b.addEventListener('click', function () {
        var id = companyIdFromEdit();
        if (!id) return alert('먼저 목록에서 업체를 불러오세요.');
        if (!confirm(confirmMsg)) return;
        postCompany(id, action);
      });
      wrap.insertBefore(b, save);
      return b;
    }
    add('승인', 'approve', '이 업체를 승인할까요?');
    add('보류', 'hold', '이 업체를 보류할까요?');
    add('거절', 'reject', '이 업체를 거절할까요?');
  }

  function markRoot(root, st, entity) {
    if (!root || !root.setAttribute) return;
    if (st && !root.getAttribute('data-cmb-status')) root.setAttribute('data-cmb-status', st);
    if (entity && !root.getAttribute('data-cmb-entity')) root.setAttribute('data-cmb-entity', entity);
    if (root.classList) {
      if (entity) root.classList.add('cmb-entity-' + entity);
      if (st) root.classList.add('cmb-status-' + st);
    }
  }

  function statusGroups() {
    var rows = [];
    document.querySelectorAll('.cmb-admin-row, .cmb-admin-toolbar').forEach(function (el) {
      if (el && rows.indexOf(el) < 0) rows.push(el);
    });
    return rows;
  }

  function statusFromRow(root) {
    if (!root) return '';
    var raw = '';
    if (root.getAttribute) raw = normalizeStatus(root.getAttribute('data-cmb-status') || '');
    if (raw) return raw;
    var cls = (root.className && String(root.className)) || '';
    var m = cls.match(/(?:^|\s)cmb-status-([a-z0-9_]+)/i);
    if (m) return normalizeStatus(m[1]);
    var meta = root.querySelector && root.querySelector('.cmb-admin-muted, .cmb-admin-meta, .cmb-admin-row-title');
    if (meta && String(meta.textContent || '').trim()) return statusFromText(meta.textContent);
    return '';
  }

  function paintStatusButtons() {
    if (painting) return;
    painting = true;
    try {
      statusGroups().forEach(function (row) {
        var st = statusFromRow(row);
        var entity = inferEntity(row, st);
        markRoot(row, st, entity);
        paintGroup(row, st, entity);
      });
      var bar = document.querySelector('.cmb-admin-toolbar');
      if (bar) {
        var st = statusFromRow(bar);
        if (!st) {
          var meta = document.querySelector('.cmb-admin-meta');
          st = statusFromText(meta && meta.textContent);
        }
        var entity = inferEntity(bar, st) || 'job';
        markRoot(bar, st, entity);
        paintGroup(bar, st, entity);
      }
      ensureCompanyEditButtons();
      var editTitle = Array.prototype.find.call(document.querySelectorAll('h2,h1,p'), function (el) {
        return /선택 업체/.test(el.textContent || '');
      });
      if (editTitle) {
        var card = editTitle.closest('.cmb-admin-card') || editTitle.parentElement;
        var st = '';
        var selected = companyIdFromEdit();
        document.querySelectorAll('.cmb-admin-row').forEach(function (row) {
          var title = row.querySelector('.cmb-admin-row-title, a');
          if (selected && title && title.textContent.indexOf('#' + selected) >= 0) {
            st = statusFromRoot(row) || statusFromText((row.querySelector('.cmb-admin-muted') || {}).textContent);
          }
        });
        if (card) {
          markRoot(card, st, 'company');
          paintGroup(card, st, 'company');
        }
      }
    } finally {
      painting = false;
    }
  }

  function hideVisually(el) {
    if (!el || el.nodeType !== 1) return;
    if (el.getAttribute && (el.getAttribute('data-cmb-filter-free') === '1' || el.getAttribute('data-cmb-form-free') === '1' || el.getAttribute('data-cmb-filter-wrap') || el.getAttribute('data-cmb-filter-host') || el.getAttribute('data-cmb-form-host'))) return;
    if (el.closest && (el.closest('[data-cmb-filter-free]') || el.closest('select[data-cmb-filter-free]') || el.closest('select[data-cmb-form-free]') || el.closest('[data-cmb-form-free]'))) return;
    el.style.setProperty('position', 'absolute', 'important');
    el.style.setProperty('width', '1px', 'important');
    el.style.setProperty('height', '1px', 'important');
    el.style.setProperty('opacity', '0', 'important');
    el.style.setProperty('overflow', 'hidden', 'important');
    el.style.setProperty('pointer-events', 'none', 'important');
    el.style.setProperty('display', 'none', 'important');
    el.setAttribute('aria-hidden', 'true');
    el.tabIndex = -1;
  }

  function unlockAdminPointer() {
    var html = document.documentElement;
    var body = document.body;
    if (html) {
      html.style.setProperty('pointer-events', 'auto', 'important');
      html.removeAttribute('data-scroll-locked');
    }
    if (body) {
      body.style.setProperty('pointer-events', 'auto', 'important');
      body.removeAttribute('data-scroll-locked');
      if (body.style.overflow === 'hidden') body.style.overflow = '';
    }
    document.querySelectorAll('[data-radix-popper-content-wrapper], [data-radix-select-content], [data-slot="select-content"]').forEach(function (el) {
      var st = (el.getAttribute('data-state') || '').toLowerCase();
      var hidden = st === 'closed' || st === 'hidden' || el.hidden || el.getAttribute('aria-hidden') === 'true';
      if (hidden) {
        el.style.setProperty('pointer-events', 'none', 'important');
        el.style.setProperty('display', 'none', 'important');
        el.style.setProperty('visibility', 'hidden', 'important');
        return;
      }
      el.style.setProperty('pointer-events', 'auto', 'important');
      el.style.setProperty('z-index', '2147483000', 'important');
    });
  }

  function isCompanyAdminPage() {
    var path = location.pathname || '';
    if (/\/admin\/maker-bids\/companies/.test(path)) return true;
    if (/\/admin\/maker-bids\/jobs/.test(path)) return false;
    var marked = document.querySelector('.cmb-admin-filter[data-cmb-filter-entity], .cmb-admin[data-cmb-filter-entity]');
    if (marked) return marked.getAttribute('data-cmb-filter-entity') === 'company';
    if (document.querySelector('.cmb-entity-company') && !document.querySelector('.cmb-entity-job')) return true;
    return false;
  }

  function filterDsId(company) {
    if (company) return 'companies';
    if (/\/admin\/maker-bids\/bids/.test(location.pathname || '')) return 'bids';
    return 'jobs';
  }

  function currentFilterValue(key) {
    try {
      if (window.G7Core && window.G7Core.state && window.G7Core.state.get) {
        return String((window.G7Core.state.get('_local.filter.' + key)) || '');
      }
    } catch (e) {}
    return '';
  }

  function currentFilterStatus() {
    return currentFilterValue('status');
  }

  var TYPE_FALLBACK = [
    ['', '전체 유형'],
    ['modeling_3d', '3D 모델링'],
    ['print_3d', '3D 출력 대행'],
    ['full_package', '풀 패키지 제작 (모델링 + 출력 + 후가공)'],
    ['character_figure', '캐릭터·피규어 커미션'],
    ['design_mockup', '디자인 목업(Mock-up) 및 시제품'],
    ['working_prototype', '워킹 프로토타입(기능성 시제품)']
  ];
  var typeOptsCache = null;
  var typeOptsLoading = false;

  function loadTypeOpts(cb) {
    if (typeOptsCache) {
      cb(typeOptsCache);
      return;
    }
    if (typeOptsLoading) {
      setTimeout(function () { loadTypeOpts(cb); }, 200);
      return;
    }
    typeOptsLoading = true;
    fetch('/api/modules/custom-maker_bids/job-types', {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json(); }).then(function (json) {
      var rows = (json && json.data) || [];
      var opts = [['', '전체 유형']];
      rows.forEach(function (t) {
        var slug = String((t && (t.slug || t.value)) || '');
        var name = String((t && (t.name || t.label)) || slug);
        if (slug) opts.push([slug, name]);
      });
      typeOptsCache = opts.length > 1 ? opts : TYPE_FALLBACK;
      typeOptsLoading = false;
      cb(typeOptsCache);
    }).catch(function () {
      typeOptsCache = TYPE_FALLBACK;
      typeOptsLoading = false;
      cb(typeOptsCache);
    });
  }

  function fillSelect(sel, opts, cur) {
    if (!sel) return;
    opts = opts || [];
    var same = sel.options && sel.options.length === opts.length;
    var i;
    if (same) {
      for (i = 0; i < opts.length; i++) {
        if (sel.options[i].value !== String(opts[i][0]) || sel.options[i].textContent !== String(opts[i][1])) {
          same = false;
          break;
        }
      }
    }
    if (!same) {
      sel.innerHTML = '';
      opts.forEach(function (o) {
        var op = document.createElement('option');
        op.value = o[0];
        op.textContent = o[1];
        sel.appendChild(op);
      });
    }
    var want = cur != null && String(cur) !== '' ? String(cur) : sel.value;
    if (want && sel.value !== want) sel.value = want;
  }

  function bindNativeFilter(sel, company, key) {
    if (!sel || sel.getAttribute('data-cmb-filter-bound') === '1') return;
    sel.setAttribute('data-cmb-filter-bound', '1');
    sel.addEventListener('change', function () {
      unlockAdminPointer();
      var map = { target: 'local' };
      map['filter.' + (key || 'status')] = sel.value;
      if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
        window.G7Core.dispatch({ handler: 'setState', params: map });
        window.G7Core.dispatch({ handler: 'refetchDataSource', params: { dataSourceId: filterDsId(company) } });
      }
    });
  }

  function statusOptsFor(bar, company) {
    var path = location.pathname || '';
    if (company) return [['', '전체'], ['pending', '보류'], ['approved', '승인'], ['rejected', '거절']];
    if (/\/admin\/maker-bids\/bids/.test(path) || (bar.getAttribute('data-cmb-filter-entity') === 'bid')) {
      return [['', '전체 상태'], ['pending', '검토중'], ['accepted', '낙찰'], ['rejected', '거절']];
    }
    return [['', '전체 상태'], ['hold', '보류'], ['draft', '임시저장'], ['request', '의뢰'], ['quote_request', '견적요청(open)'], ['awarded', '낙찰'], ['disputed', '분쟁조정'], ['done', '완료'], ['cancelled', '취소']];
  }

  function hideG7FilterSelects(bar) {
    bar.querySelectorAll('[role="combobox"], [data-slot="trigger"], [data-slot="select-trigger"], [aria-haspopup="listbox"]').forEach(function (el) {
      if (el.closest && (el.closest('[data-cmb-filter-wrap]') || el.closest('[data-cmb-filter-free]'))) return;
      hideVisually(el);
    });
    bar.querySelectorAll('button, [role="combobox"]').forEach(function (el) {
      if (el.closest && el.closest('[data-cmb-filter-host], .cmb-admin-filter-status, .cmb-admin-filter-type')) {
        if (el.tagName === 'SELECT') return;
        if (el.getAttribute('data-cmb-filter-free') === '1') return;
        if ((el.textContent || '').indexOf('필터') >= 0) return;
        hideVisually(el);
      }
    });
  }

  function ensureNativeSelect(bar, key, opts, company) {
    var host = bar.querySelector('[data-cmb-filter-host="' + key + '"]');
    var wrap = bar.querySelector('[data-cmb-filter-wrap="' + key + '"]');
    if (!wrap && host) {
      wrap = host;
      wrap.setAttribute('data-cmb-filter-wrap', key);
      wrap.classList.add('cmb-admin-filter-native-wrap');
    }
    if (!wrap) {
      wrap = document.createElement('div');
      wrap.setAttribute('data-cmb-filter-wrap', key);
      wrap.className = 'cmb-admin-filter-native-wrap cmb-admin-filter-field ' + (key === 'type' ? 'cmb-admin-filter-type' : 'cmb-admin-filter-status');
      var lab = document.createElement('p');
      lab.className = 'cmb-admin-label';
      lab.textContent = key === 'type' ? '유형' : '상태';
      wrap.appendChild(lab);
      var go = bar.querySelector('.cmb-admin-filter-go');
      bar.insertBefore(wrap, go || bar.firstChild);
    }
    var sel = wrap.querySelector('select[data-cmb-filter-free][data-cmb-filter-key="' + key + '"]') ||
      wrap.querySelector('select[data-cmb-filter-free]');
    if (sel && sel.getAttribute('data-cmb-filter-key') && sel.getAttribute('data-cmb-filter-key') !== key) sel = null;
    if (!sel) {
      sel = document.createElement('select');
      sel.setAttribute('data-cmb-filter-free', '1');
      sel.setAttribute('data-cmb-filter-key', key);
      sel.setAttribute('name', 'cmb_filter_' + key);
      sel.className = 'cmb-admin-filter-native';
      wrap.appendChild(sel);
    }
    fillSelect(sel, opts, currentFilterValue(key));
    bindNativeFilter(sel, company, key);
    return sel;
  }

  function ensureAdminFilterSelect() {
    document.querySelectorAll('.cmb-admin-filter').forEach(function (bar) {
      var company = (bar.getAttribute('data-cmb-filter-entity') === 'company') ||
        (bar.getAttribute('data-cmb-filter-entity') !== 'job' && bar.getAttribute('data-cmb-filter-entity') !== 'bid' && isCompanyAdminPage());
      hideG7FilterSelects(bar);
      ensureNativeSelect(bar, 'status', statusOptsFor(bar, company), company);
      var wantType = bar.getAttribute('data-cmb-filter-entity') === 'job' ||
        bar.querySelector('[data-cmb-filter-host="type"], .cmb-admin-filter-type');
      if (wantType && !company) {
        if (typeOptsCache) {
          ensureNativeSelect(bar, 'type', typeOptsCache, company);
        } else {
          ensureNativeSelect(bar, 'type', TYPE_FALLBACK, company);
          loadTypeOpts(function (opts) {
            var native = bar.querySelector('select[data-cmb-filter-key="type"]');
            if (native) fillSelect(native, opts, currentFilterValue('type'));
          });
        }
      }
    });
    unlockAdminPointer();
  }

  function injectStatusPaintCss() {
    if (document.getElementById('cmb-admin-status-inline')) return;
    var s = document.createElement('style');
    s.id = 'cmb-admin-status-inline';
    s.textContent =
      'html.cmb-admin-dark .cmb-admin [data-cmb-kind="approve"][data-cmb-active="1"],' +
      'html.cmb-admin-ui .cmb-admin [data-cmb-kind="approve"][data-cmb-active="1"]{' +
      'background:rgb(5,150,105)!important;background-color:rgb(5,150,105)!important;color:#fff!important;border-color:rgb(4,120,87)!important}' +
      'html.cmb-admin-dark .cmb-admin [data-cmb-kind="hold"][data-cmb-active="1"],' +
      'html.cmb-admin-ui .cmb-admin [data-cmb-kind="hold"][data-cmb-active="1"]{' +
      'background:rgb(217,119,6)!important;background-color:rgb(217,119,6)!important;color:#fff!important;border-color:rgb(180,83,9)!important}' +
      'html.cmb-admin-dark .cmb-admin [data-cmb-kind="reject"][data-cmb-active="1"],' +
      'html.cmb-admin-ui .cmb-admin [data-cmb-kind="reject"][data-cmb-active="1"]{' +
      'background:rgb(220,38,38)!important;background-color:rgb(220,38,38)!important;color:#fff!important;border-color:rgb(185,28,28)!important}' +
      '.cmb-admin-filter-field.cmb-admin-select-host select.cmb-admin-filter-native,' +
      '.cmb-admin-filter select[data-cmb-filter-free]{display:block!important;appearance:auto!important;-webkit-appearance:menulist!important;' +
      'width:12rem!important;min-width:12rem!important;max-width:14rem!important;height:2.25rem!important;opacity:1!important;pointer-events:auto!important}' +
      '.cmb-admin-uploader [class*="border-dashed"],.cmb-admin-uploader-compact [class*="border-dashed"],' +
      '.cmb-admin .cmb-order-uploader-inner [class*="border-dashed"]{min-height:0!important;height:auto!important;padding:0.3rem 0.45rem!important}' +
      '.cmb-admin-uploader [class*="border-dashed"] svg,.cmb-admin-uploader-compact [class*="border-dashed"] svg{display:none!important}';
    (document.head || document.documentElement).appendChild(s);
  }

  function hideLegacySizes() {
    document.querySelectorAll('.cmb-legacy-size-row, [data-cmb-legacy-size]').forEach(function (el) {
      el.style.display = 'none';
    });
    ['size_w', 'size_d', 'size_h'].forEach(function (name) {
      document.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
        var wrap = el.closest ? (el.closest('.cmb-admin-inline') || el.parentElement) : el.parentElement;
        el.style.display = 'none';
        if (wrap && wrap !== document.body) wrap.style.display = 'none';
      });
    });
  }

  function bindStatusPress() {
    if (document.documentElement.getAttribute('data-cmb-status-press')) return;
    document.documentElement.setAttribute('data-cmb-status-press', '1');
    function targetBtn(e) {
      var el = e.target && e.target.closest ? e.target.closest('.cmb-btn-approve, .cmb-btn-hold, .cmb-btn-reject') : null;
      if (!el || !el.closest || !el.closest('.cmb-admin')) return null;
      return el;
    }
    function press(btn) {
      if (!btn) return;
      btn.setAttribute('data-cmb-pressed', '1');
      btn.classList.add('is-pressed');
      if (btn.__cmbPressT) clearTimeout(btn.__cmbPressT);
      btn.__cmbPressT = setTimeout(function () {
        btn.removeAttribute('data-cmb-pressed');
        btn.classList.remove('is-pressed');
      }, 360);
    }
    document.addEventListener('pointerdown', function (e) { press(targetBtn(e)); }, true);
    document.addEventListener('keydown', function (e) {
      if (e.key !== ' ' && e.key !== 'Enter') return;
      press(targetBtn(e));
    }, true);
  }

  function observeAdminStatus() {
    if (window.__cmbAdminStatusObs) return;
    var root = document.querySelector('.cmb-admin') || document.body;
    if (!root || typeof MutationObserver === 'undefined') return;
    window.__cmbAdminStatusObs = new MutationObserver(function () {
      if (painting) return;
      if (window.__cmbAdminStatusPaintT) clearTimeout(window.__cmbAdminStatusPaintT);
      window.__cmbAdminStatusPaintT = setTimeout(function () {
        paintStatusButtons();
        hideLegacySizes();
        ensureListBoxes();
        ensureAdminFilterSelect();
        paintOpenAdminMenus();
      }, 60);
    });
    window.__cmbAdminStatusObs.observe(root, { childList: true, subtree: true });
  }

  function parseSizes(raw) {
    if (Array.isArray(raw) && raw.length) {
      return raw.map(function (r) {
        return { name: r && r.name != null ? String(r.name) : '', w: r && r.w != null ? String(r.w) : '', d: r && r.d != null ? String(r.d) : '', h: r && r.h != null ? String(r.h) : '' };
      });
    }
    if (typeof raw === 'string' && raw.trim()) {
      try { return parseSizes(JSON.parse(raw)); } catch (e) {}
    }
    return [{ name: '', w: '', d: '', h: '' }];
  }

  function makeRow(item, canRemove) {
    var wrap = document.createElement('div');
    wrap.setAttribute('data-cmb-size-row', '1');
    wrap.className = 'cmb-size-row flex flex-wrap items-center gap-2';
    wrap.innerHTML =
      '<input data-cmb-size-name type="text" maxlength="80" placeholder="이름" class="' + CLS + '">' +
      '<input data-cmb-size-w type="number" min="0" placeholder="W" class="' + CLS + '">' +
      '<span>×</span>' +
      '<input data-cmb-size-d type="number" min="0" placeholder="D" class="' + CLS + '">' +
      '<span>×</span>' +
      '<input data-cmb-size-h type="number" min="0" placeholder="H" class="' + CLS + '">' +
      '<button type="button" data-cmb-size-remove class="px-2.5 py-2 text-sm rounded-lg border">삭제</button>';
    wrap.querySelector('[data-cmb-size-name]').value = item.name || '';
    wrap.querySelector('[data-cmb-size-w]').value = item.w || '';
    wrap.querySelector('[data-cmb-size-d]').value = item.d || '';
    wrap.querySelector('[data-cmb-size-h]').value = item.h || '';
    if (!canRemove) wrap.querySelector('[data-cmb-size-remove]').style.visibility = 'hidden';
    return wrap;
  }

  function collect(list) {
    var rows = [];
    list.querySelectorAll('[data-cmb-size-row]').forEach(function (row) {
      rows.push({
        name: row.querySelector('[data-cmb-size-name]').value,
        w: row.querySelector('[data-cmb-size-w]').value === '' ? null : Number(row.querySelector('[data-cmb-size-w]').value),
        d: row.querySelector('[data-cmb-size-d]').value === '' ? null : Number(row.querySelector('[data-cmb-size-d]').value),
        h: row.querySelector('[data-cmb-size-h]').value === '' ? null : Number(row.querySelector('[data-cmb-size-h]').value)
      });
    });
    return rows;
  }

  function enhanceSizes() {
    var ta = document.querySelector('textarea[name="sizes_json"]');
    if (!ta || ta.getAttribute('data-cmb-size-ui')) return;
    ta.setAttribute('data-cmb-size-ui', '1');
    ta.style.display = 'none';
    var items = parseSizes(ta.value || ta.getAttribute('placeholder'));
    var root = document.createElement('div');
    var list = document.createElement('div');
    items.forEach(function (it) { list.appendChild(makeRow(it, items.length > 1)); });
    var add = document.createElement('button');
    add.type = 'button';
    add.textContent = '추가';
    add.className = 'px-3 py-1.5 text-sm rounded-lg border';
    root.appendChild(list);
    root.appendChild(add);
    ta.parentNode.insertBefore(root, ta);
    function sync() { ta.value = JSON.stringify(collect(list)); ta.dispatchEvent(new Event('input', { bubbles: true })); }
    add.addEventListener('click', function (e) { e.preventDefault(); list.appendChild(makeRow({ name: '', w: '', d: '', h: '' }, true)); sync(); });
    list.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('[data-cmb-size-remove]');
      if (!btn || list.querySelectorAll('[data-cmb-size-row]').length <= 1) return;
      e.preventDefault();
      btn.closest('[data-cmb-size-row]').remove();
      sync();
    });
    list.addEventListener('input', sync);
    sync();
  }

  function menuIsOpen(el) {
    if (!el || el.nodeType !== 1) return false;
    var st = String(el.getAttribute('data-state') || '').toLowerCase();
    if (st === 'closed' || st === 'hidden') return false;
    if (el.hidden || el.getAttribute('aria-hidden') === 'true') return false;
    return true;
  }

  function paintOpenAdminMenus() {
    if (!document.documentElement.classList.contains('cmb-admin-ui')) return;
    document.querySelectorAll('[role="listbox"], [data-radix-select-content], [data-slot="select-content"], [data-radix-popper-content-wrapper], [data-radix-select-viewport]').forEach(function (el) {
      if (!menuIsOpen(el)) return;
      el.classList.add('cmb-admin-listbox');
      el.style.setProperty('background', '#0f172a', 'important');
      el.style.setProperty('background-color', '#0f172a', 'important');
      el.style.setProperty('color', '#f8fafc', 'important');
      el.style.setProperty('-webkit-text-fill-color', '#f8fafc', 'important');
      el.style.setProperty('opacity', '1', 'important');
      el.style.setProperty('visibility', 'visible', 'important');
      el.style.setProperty('z-index', '2147483000', 'important');
      el.style.setProperty('border-color', 'rgba(255,255,255,0.22)', 'important');
      var nodes = el.querySelectorAll('*');
      var i;
      for (i = 0; i < nodes.length; i++) {
        var child = nodes[i];
        var slot = child.getAttribute ? String(child.getAttribute('data-slot') || '') : '';
        if (slot === 'select-item-indicator' || (child.closest && child.closest('[data-slot="select-item-indicator"]'))) continue;
        var tag = (child.tagName || '').toUpperCase();
        if (tag === 'SVG' || tag === 'PATH' || tag === 'CIRCLE') continue;
        child.style.setProperty('color', '#f8fafc', 'important');
        child.style.setProperty('-webkit-text-fill-color', '#f8fafc', 'important');
        child.style.setProperty('opacity', '1', 'important');
        child.style.setProperty('visibility', 'visible', 'important');
        if (child.getAttribute('role') === 'option' || slot === 'select-item' || tag === 'LI') {
          var hi = child.getAttribute('data-highlighted') != null || child.getAttribute('data-state') === 'checked';
          child.style.setProperty('background', hi ? 'rgba(255,255,255,0.14)' : 'transparent', 'important');
          child.style.setProperty('background-color', hi ? 'rgba(255,255,255,0.14)' : 'transparent', 'important');
        } else if (slot === 'select-viewport' || tag === 'DIV') {
          var cls = String(child.className || '');
          if (/bg-white|bg-popover|bg-background|bg-card/.test(cls) || child.getAttribute('data-radix-select-viewport') != null) {
            child.style.setProperty('background', '#0f172a', 'important');
            child.style.setProperty('background-color', '#0f172a', 'important');
          }
        }
      }
    });
  }

  function observeAdminMenus() {
    if (window.__cmbAdminMenuObs || typeof MutationObserver === 'undefined') return;
    window.__cmbAdminMenuObs = new MutationObserver(function () {
      if (window.__cmbAdminMenuPaintT) clearTimeout(window.__cmbAdminMenuPaintT);
      window.__cmbAdminMenuPaintT = setTimeout(paintOpenAdminMenus, 16);
    });
    window.__cmbAdminMenuObs.observe(document.documentElement, { childList: true, subtree: true });
  }

  function injectPortalCss() {
    var s = document.getElementById('cmb-admin-select-portal-css');
    if (s && s.getAttribute('data-cmb-v') === '38') return;
    if (!s) {
      s = document.createElement('style');
      s.id = 'cmb-admin-select-portal-css';
      (document.head || document.documentElement).appendChild(s);
    }
    s.setAttribute('data-cmb-v', '38');
      s.textContent =
      'html.cmb-admin-ui,html.cmb-admin-ui body,html.cmb-admin-ui .cmb-admin,html.cmb-admin-ui select{color-scheme:dark}' +
      'html.cmb-admin-ui,html.cmb-admin-ui body,html.cmb-admin-ui body[data-scroll-locked]{pointer-events:auto!important}' +
      'html.cmb-admin-ui [data-state="closed"][data-radix-popper-content-wrapper],' +
      'html.cmb-admin-ui [data-state="closed"][data-radix-select-content],' +
      'html.cmb-admin-ui [data-state="closed"][role="listbox"]{display:none!important;pointer-events:none!important;visibility:hidden!important}' +
      'html.cmb-admin-ui [role="listbox"],html.cmb-admin-ui [data-slot="select-content"],html.cmb-admin-ui [data-radix-select-content],' +
      'html.cmb-admin-ui [data-radix-select-viewport],html.cmb-admin-ui [data-radix-popper-content-wrapper]:not([data-state="closed"]),' +
      'html.cmb-admin-ui .cmb-admin-listbox{' +
      'background:#0f172a!important;background-color:#0f172a!important;color:#f8fafc!important;' +
      '-webkit-text-fill-color:#f8fafc!important;border:1px solid rgba(255,255,255,0.22)!important;' +
      'box-shadow:0 12px 40px rgba(0,0,0,0.55)!important;z-index:2147483000!important;opacity:1!important;visibility:visible!important;' +
      'pointer-events:auto!important;min-width:12rem!important;max-width:min(90vw,28rem)!important;' +
      'white-space:nowrap!important;word-break:keep-all!important;overflow-x:hidden!important;overflow-y:auto!important;box-sizing:border-box!important}' +
      'html.cmb-admin-ui [role="option"],html.cmb-admin-ui [data-slot="select-item"],html.cmb-admin-ui [role="option"] span,' +
      'html.cmb-admin-ui [role="option"] p,html.cmb-admin-ui [data-slot="select-item"] span,' +
      'html.cmb-admin-ui [role="option"] [class*="text-"],html.cmb-admin-ui .cmb-admin-listbox [role="option"]{' +
      'color:#f8fafc!important;-webkit-text-fill-color:#f8fafc!important;opacity:1!important;visibility:visible!important;' +
      'white-space:nowrap!important;word-break:keep-all!important}' +
      'html.cmb-admin-ui [role="option"],html.cmb-admin-ui [data-slot="select-item"]{' +
      'background:transparent!important;display:flex!important;flex-direction:row!important;align-items:center!important;' +
      'min-width:100%!important;padding:0.45rem 0.8rem!important}' +
      'html.cmb-admin-ui [role="option"][data-highlighted],html.cmb-admin-ui [role="option"]:hover,' +
      'html.cmb-admin-ui [data-slot="select-item"][data-highlighted]{background:rgba(255,255,255,0.14)!important;color:#fff!important}' +
      'html.cmb-admin-ui .cmb-admin [role="combobox"],html.cmb-admin-ui .cmb-admin-select-host button,' +
      'html.cmb-admin-ui .cmb-admin-select-host [data-slot="trigger"]{' +
      'color:#f8fafc!important;background:#1e293b!important;border-color:rgba(255,255,255,0.28)!important}' +
      'html.cmb-admin-ui .cmb-admin [role="combobox"] *:not(svg):not(path),' +
      'html.cmb-admin-ui .cmb-admin-select-host button *:not(svg):not(path){color:#f8fafc!important;-webkit-text-fill-color:#f8fafc!important;opacity:1!important}' +
      'html.cmb-admin-ui select,html.cmb-admin-ui .cmb-admin-filter-native{color-scheme:dark;color:#f8fafc;background:#1e293b}' +
      'html.cmb-admin-ui select option,html.cmb-admin-ui .cmb-admin-filter-native option{color:#0f172a;background:#f8fafc}' +
      '.cmb-admin-row-actions,.cmb-admin-row-actions a,.cmb-admin-row-actions button{position:relative;z-index:2;pointer-events:auto!important}';
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
  function g7Get(path) {
    try {
      if (window.G7Core && window.G7Core.state && typeof window.G7Core.state.get === 'function') {
        return window.G7Core.state.get(path);
      }
    } catch (e) {}
    return null;
  }

  function ensureListBoxes() {
    document.querySelectorAll('.cmb-admin-list > .cmb-admin-row, .cmb-admin-list .cmb-admin-row').forEach(function (row) {
      row.classList.add('cmb-list-item', 'cmb-admin-list-item');
    });
  }

  function harvestNamedInto(prefix) {
    var root = prefix === 'edit'
      ? (document.querySelector('[data-cmb-company-edit]') || document.querySelector('.cmb-admin-company-edit') || document.querySelector('.cmb-admin'))
      : document.querySelector('.cmb-admin');
    if (!root) return {};
    var map = {};
    var selectKeys = { type: 1, status: 1, audience: 1, kind: 1, nav_insert: 1, bid_allow: 1, default_job_status: 1 };
    var jobTypes = [];
    root.querySelectorAll('[name]').forEach(function (el) {
      var name = el.getAttribute('name');
      if (!name || el.type === 'file') return;
      if (name.indexOf('cmb_filter_') === 0) return;
      if (name === 'job_types' || name.indexOf('job_type_') === 0) {
        if (el.type === 'checkbox' && el.checked) jobTypes.push(name.slice('job_type_'.length));
        return;
      }
      if (el.type === 'checkbox') {
        map[prefix + '.' + name] = !!el.checked;
        return;
      }
      var tag = (el.tagName || '').toUpperCase();
      if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') return;
      var val = el.value;
      if (prefix !== 'edit' && selectKeys[name] && String(val || '').trim() === '') return;
      if (prefix !== 'edit' && String(val || '').trim() === '') return;
      map[prefix + '.' + name] = val;
    });
    root.querySelectorAll('[data-cmb-job-type]').forEach(function (el) {
      if (el.checked) jobTypes.push(el.getAttribute('data-cmb-job-type'));
    });
    jobTypes = jobTypes.filter(function (v, i, a) { return v && a.indexOf(v) === i; });
    if (prefix === 'edit' && root.querySelector('[data-cmb-job-type]')) {
      if (!jobTypes.length) {
        var hidden = root.querySelector('input[name="job_types"]');
        var raw = hidden && hidden.value;
        if (raw) {
          try {
            var parsed = raw.charAt(0) === '[' ? JSON.parse(raw) : raw;
            if (Array.isArray(parsed)) jobTypes = parsed.map(String);
          } catch (eJT) {}
        }
      }
      map[prefix + '.job_types'] = jobTypes;
    }
    if (prefix === 'edit') {
      var keepId = companyIdFromEdit();
      if (!map['edit.id'] && keepId) map['edit.id'] = keepId;
      else if (map['edit.id']) rememberEditId(map['edit.id']);
    }
    return map;
  }

  function paintSettingsControls() {
    var form = g7Get('_local.form') || g7Get('form') || {};
    if (!form || typeof form !== 'object') return;
    Object.keys(form).forEach(function (k) {
      var el = document.querySelector('.cmb-admin [name="' + k + '"]');
      if (!el) return;
      if (el.type === 'checkbox') {
        el.checked = !!(form[k] === true || form[k] === 1 || form[k] === '1' || form[k] === 'true');
      } else if ((el.tagName || '').toUpperCase() === 'TEXTAREA' || ((el.tagName || '').toUpperCase() === 'INPUT' && el.type !== 'checkbox')) {
        if (document.activeElement === el) return;
        el.value = form[k] == null ? '' : String(form[k]);
      }
    });
  }

  function bindSettingsHarvest() {
    if (document.documentElement.getAttribute('data-cmb-settings-bound')) return;
    if (!/\/admin\/maker-bids\/settings/.test(location.pathname || '')) return;
    document.documentElement.setAttribute('data-cmb-settings-bound', '1');
    document.addEventListener(
      'click',
      function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('button') : null;
        if (!btn) return;
        var label = (btn.textContent || '').replace(/\s+/g, ' ').trim();
        if (label.indexOf('설정 저장') === -1) return;
        var map = harvestNamedInto('form');
        // Keep select values already in local state when harvest skipped empties.
        var cur = g7Get('_local.form') || {};
        ['nav_insert', 'default_job_status', 'bid_allow', 'provided_extensions', 'nav_label'].forEach(function (k) {
          if ((map['form.' + k] == null || map['form.' + k] === '') && cur[k] != null && cur[k] !== '') {
            map['form.' + k] = cur[k];
          }
        });
        if (Object.keys(map).length) setLocal(map);
      },
      true
    );
    document.addEventListener(
      'change',
      function (e) {
        var el = e.target;
        if (!el || !el.getAttribute) return;
        if (!el.closest || !el.closest('.cmb-admin')) return;
        var name = el.getAttribute('name');
        if (!name) return;
        var map = {};
        if (el.type === 'checkbox') map['form.' + name] = !!el.checked;
        else map['form.' + name] = el.value;
        setLocal(map);
      },
      true
    );
    paintSettingsControls();
  }

  function bindFilterGo() {
    if (document.documentElement.getAttribute('data-cmb-filter-go')) return;
    document.documentElement.setAttribute('data-cmb-filter-go', '1');
    document.addEventListener('click', function (e) {
      unlockAdminPointer();
      var go = e.target && e.target.closest ? e.target.closest('.cmb-admin-filter-go, .cmb-admin-filter > button') : null;
      if (!go || (go.textContent || '').indexOf('필터') < 0) return;
      var bar = go.closest('.cmb-admin-filter') || document.querySelector('.cmb-admin-filter');
      if (!bar) return;
      var map = { target: 'local' };
      bar.querySelectorAll('select[data-cmb-filter-free]').forEach(function (sel) {
        var key = sel.getAttribute('data-cmb-filter-key') || (sel.name === 'cmb_filter_type' ? 'type' : 'status');
        map['filter.' + key] = sel.value;
      });
      if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
        window.G7Core.dispatch({ handler: 'setState', params: map });
      }
    }, true);
  }

  function csrfToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    if (m && m.getAttribute('content')) return m.getAttribute('content');
    var match = String(document.cookie || '').match(/(?:^|; )XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
  }

  function idFromRow(row) {
    if (!row) return '';
    var title = row.querySelector('.cmb-admin-row-title, a');
    var m = title ? String(title.textContent || '').match(/#(\d+)/) : null;
    if (m) return m[1];
    var href = title && title.getAttribute ? String(title.getAttribute('href') || '') : '';
    m = href.match(/(\d+)\/?$/);
    return m ? m[1] : '';
  }

  function adminFetch(url, method, okMsg, body, extra) {
    method = method || 'POST';
    extra = extra || {};
    var headers = csrfHeaderMap();
    if (body != null) headers['Content-Type'] = 'application/json';
    var payload = body == null ? undefined : (typeof body === 'string' ? body : JSON.stringify(body));
    function onOk() {
      if (extra.navigate) {
        if (okMsg && window.G7Core && window.G7Core.dispatch) {
          window.G7Core.dispatch({ handler: 'toast', params: { type: 'success', message: okMsg } });
        }
        location.href = extra.navigate;
        return;
      }
      if (window.G7Core && window.G7Core.dispatch) {
        if (okMsg) window.G7Core.dispatch({ handler: 'toast', params: { type: 'success', message: okMsg } });
        window.G7Core.dispatch({ handler: 'refetchDataSource', params: { dataSourceId: 'job' } });
        window.G7Core.dispatch({ handler: 'refetchDataSource', params: { dataSourceId: 'jobs' } });
        window.G7Core.dispatch({ handler: 'refetchDataSource', params: { dataSourceId: 'companies' } });
      } else {
        location.reload();
      }
    }
    function onErr(msg) {
      if (window.G7Core && window.G7Core.dispatch) {
        window.G7Core.dispatch({ handler: 'toast', params: { type: 'error', message: msg } });
      }
    }
    function rawFetch() {
      return fetch(url, {
        method: method,
        credentials: 'include',
        headers: headers,
        body: payload
      }).then(function (res) {
        if (!res.ok) {
          return res.json().catch(function () { return {}; }).then(function (j) {
            var msg = (j && (j.message || (j.error && j.error.message))) || '처리에 실패했습니다.';
            if (res.status === 401) msg = '로그인이 필요합니다. 새로고침 후 다시 시도해 주세요.';
            onErr(msg);
            throw new Error(msg);
          });
        }
        onOk();
      });
    }
    try {
      var api = window.G7Core && window.G7Core.api;
      var m = String(method).toLowerCase();
      if (api) {
        var via = null;
        if (m === 'delete' && typeof api.delete === 'function') via = api.delete(url);
        else if (m === 'post' && typeof api.post === 'function') via = api.post(url, body && typeof body === 'object' ? body : {});
        else if (m === 'patch' && typeof api.patch === 'function') via = api.patch(url, body && typeof body === 'object' ? body : {});
        if (via && typeof via.then === 'function') {
          return via.then(function () { onOk(); }).catch(function () { return rawFetch(); });
        }
      }
    } catch (eApi) {}
    return rawFetch();
  }

  function mergeHarvest(prefix, map) {
    var cur = g7Get('_local.' + prefix) || {};
    var out = {};
    var k;
    if (cur && typeof cur === 'object') {
      for (k in cur) {
        if (Object.prototype.hasOwnProperty.call(cur, k)) out[k] = cur[k];
      }
    }
    Object.keys(map || {}).forEach(function (key) {
      if (key.indexOf(prefix + '.') === 0) out[key.slice(prefix.length + 1)] = map[key];
    });
    return out;
  }

  function fireUpload(name) {
    try {
      if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
        window.G7Core.dispatch({ handler: 'fireEvent', params: { event: name } });
      }
    } catch (eUp) {}
  }

  function bindRowActions() {
    if (document.documentElement.getAttribute('data-cmb-row-actions')) return;
    document.documentElement.setAttribute('data-cmb-row-actions', '1');
    document.addEventListener('pointerdown', function () { unlockAdminPointer(); }, true);
    document.addEventListener('click', function (e) {
      unlockAdminPointer();
      var t = e.target;
      if (!t || !t.closest) return;
      if (!t.closest('.cmb-admin')) return;
      if (t.closest('a')) return;
      var btn = t.closest('button, [role="button"]');
      if (!btn) return;
      if (btn.getAttribute('data-cmb-existing-delete') || btn.closest('.cmb-existing-item, .cmb-existing-files, .cmb-existing-del')) return;
      var label = (btn.textContent || '').replace(/\s+/g, ' ').trim();
      var kind = kindFromEl(btn);
      if (label.indexOf('불러오기') >= 0) {
        var loadId = idFromRow(btn.closest('.cmb-admin-row'));
        if (loadId) rememberEditId(loadId);
        return;
      }
      var row = btn.closest('.cmb-admin-row');
      var toolbar = btn.closest('.cmb-admin-toolbar, [data-cmb-company-edit], [data-cmb-job-edit]');
      var inCompanyForm = !!(btn.closest('[data-cmb-company-edit], .cmb-admin-company-toolbar'));
      var inJobForm = !!(btn.closest('[data-cmb-job-edit], .cmb-admin-toolbar.cmb-entity-job'));
      var inJobSave = label.indexOf('의뢰 저장') >= 0 || kind === 'save';
      var inCoSave = label.indexOf('선택 업체 저장') >= 0;
      if (!row && !toolbar && !inJobSave && !inCoSave) return;
      var entity = '';
      var id = '';
      if (row) {
        entity = inferEntity(row, statusFromRow(row));
        id = idFromRow(row);
      }
      if (toolbar && !entity) entity = inferEntity(toolbar, statusFromRow(toolbar));
      if (inCompanyForm || inCoSave) {
        entity = 'company';
        id = id || companyIdFromEdit();
      } else if (inJobForm || inJobSave || jobIdFromPath()) {
        if (!row) {
          entity = 'job';
          id = id || jobIdFromPath();
        }
      }
      if (inJobSave) {
        entity = 'job';
        id = id || jobIdFromPath();
        if (!id) return;
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
        var jobMap = harvestNamedInto('form');
        if (Object.keys(jobMap).length) setLocal(jobMap);
        fireUpload('upload:maker_bids_admin_images');
        fireUpload('upload:maker_bids_admin_archives');
        adminFetch('/api/modules/custom-maker_bids/admin/jobs/' + encodeURIComponent(id), 'PATCH', '의뢰를 저장했습니다.', mergeHarvest('form', jobMap));
        return;
      }
      if (inCoSave) {
        id = id || companyIdFromEdit();
        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
        if (!id) {
          alert('먼저 목록에서 업체를 불러오세요.');
          return;
        }
        rememberEditId(id);
        var coMap = harvestNamedInto('edit');
        if (Object.keys(coMap).length) setLocal(coMap);
        var edit = mergeHarvest('edit', coMap);
        edit.id = id;
        fireUpload('upload:maker_bids_admin_logo');
        adminFetch('/api/modules/custom-maker_bids/admin/companies/' + encodeURIComponent(id), 'PATCH', '저장했습니다.', edit);
        return;
      }
      if (!id) return;
      var action = '';
      var method = 'POST';
      var confirmMsg = '';
      var okMsg = '';
      var base = entity === 'company'
        ? '/api/modules/custom-maker_bids/admin/companies/'
        : '/api/modules/custom-maker_bids/admin/jobs/';
      if (kind === 'approve' || /^승인/.test(label)) {
        action = 'approve';
        confirmMsg = entity === 'company' ? '이 업체를 승인할까요?' : '승인하여 견적요청(open)으로 공개할까요?';
        okMsg = '승인했습니다.';
      } else if (kind === 'hold' || /^보류/.test(label)) {
        action = 'hold';
        confirmMsg = entity === 'company' ? '이 업체를 보류할까요?' : '이 의뢰를 보류할까요?';
        okMsg = '보류했습니다.';
      } else if (kind === 'reject' || /^거절/.test(label)) {
        action = 'reject';
        confirmMsg = '이 업체를 거절할까요?';
        okMsg = '거절했습니다.';
      } else if (kind === 'dispute' || /^분쟁/.test(label)) {
        action = 'dispute';
        confirmMsg = '이 의뢰를 분쟁조정 상태로 바꿀까요?';
        okMsg = '분쟁조정 상태입니다.';
      } else if ((kind === 'cancel' || /^취소/.test(label)) && entity !== 'company') {
        action = 'cancel';
        confirmMsg = '이 의뢰를 취소할까요?';
        okMsg = '취소했습니다.';
      } else if (kind === 'delete' || /^삭제/.test(label)) {
        action = '';
        method = 'DELETE';
        confirmMsg = entity === 'job' && !row ? '의뢰와 입찰을 삭제할까요?' : '삭제할까요?';
        okMsg = '삭제했습니다.';
      } else {
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
      if (!confirm(confirmMsg)) return;
      var body = null;
      if (entity === 'company' && (action === 'hold' || action === 'reject')) {
        var harvested = harvestNamedInto('edit');
        if (Object.keys(harvested).length) setLocal(harvested);
        var editBody = mergeHarvest('edit', harvested);
        body = {
          hold_reason: editBody.hold_reason || '',
          rejected_reason: editBody.rejected_reason || '',
          admin_memo: editBody.admin_memo || ''
        };
      }
      var extra = {};
      if (method === 'DELETE' && entity === 'job' && !row) extra.navigate = '/admin/maker-bids';
      adminFetch(base + encodeURIComponent(id) + (action ? '/' + action : ''), method, okMsg, body, extra);
    }, true);
  }

  function bindCompanyHarvest() {
    if (document.documentElement.getAttribute('data-cmb-co-harvest')) return;
    if (!/\/admin\/maker-bids\/companies/.test(location.pathname || '') && !document.querySelector('[data-cmb-company-edit]')) return;
    document.documentElement.setAttribute('data-cmb-co-harvest', '1');
    function pushDom() {
      var map = harvestNamedInto('edit');
      var keepId = companyIdFromEdit();
      if (keepId) map['edit.id'] = keepId;
      if (Object.keys(map).length) setLocal(map);
    }
    document.addEventListener(
      'click',
      function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('button') : null;
        if (!btn) return;
        var label = (btn.textContent || '').replace(/\s+/g, ' ').trim();
        if (label.indexOf('선택 업체 저장') === -1 && label.indexOf('승인') !== 0 && label.indexOf('보류') !== 0 && label.indexOf('거절') !== 0) return;
        if (!btn.closest('[data-cmb-company-edit], .cmb-admin-company-toolbar')) return;
        pushDom();
      },
      true
    );
    document.addEventListener(
      'input',
      function (e) {
        var el = e.target;
        if (!el || !el.getAttribute || !el.closest) return;
        if (!el.closest('[data-cmb-company-edit]')) return;
        var name = el.getAttribute('name');
        if (!name || name.indexOf('cmb_filter_') === 0) return;
        var map = {};
        if (el.type === 'checkbox') map['edit.' + name] = !!el.checked;
        else map['edit.' + name] = el.value;
        var keepId = companyIdFromEdit();
        if (keepId) map['edit.id'] = keepId;
        setLocal(map);
      },
      true
    );
    document.addEventListener(
      'change',
      function (e) {
        var el = e.target;
        if (!el || !el.getAttribute || !el.closest) return;
        if (!el.closest('[data-cmb-company-edit]')) return;
        var name = el.getAttribute('name') || '';
        var map = {};
        if (el.getAttribute('data-cmb-job-type')) {
          var slugs = [];
          document.querySelectorAll('[data-cmb-company-edit] [data-cmb-job-type]:checked').forEach(function (b) {
            slugs.push(b.getAttribute('data-cmb-job-type'));
          });
          map['edit.job_types'] = slugs;
          var keepJobId = companyIdFromEdit();
          if (keepJobId) map['edit.id'] = keepJobId;
          setLocal(map);
          return;
        }
        if (!name || name.indexOf('cmb_filter_') === 0) return;
        if (el.type === 'checkbox') map['edit.' + name] = !!el.checked;
        else map['edit.' + name] = el.value;
        var keepId = companyIdFromEdit();
        if (keepId) map['edit.id'] = keepId;
        setLocal(map);
      },
      true
    );
  }

  function currentEditValue(key) {
    try {
      if (window.G7Core && window.G7Core.state && window.G7Core.state.get) {
        var v = window.G7Core.state.get('_local.edit.' + key);
        if (v != null && v !== '') return String(v);
        var ed = window.G7Core.state.get('_local.edit') || {};
        if (ed && ed[key] != null && ed[key] !== '') return String(ed[key]);
      }
    } catch (e) {}
    return '';
  }

  function bindEditNative(sel, key) {
    if (!sel || sel.getAttribute('data-cmb-edit-bound') === '1') return;
    sel.setAttribute('data-cmb-edit-bound', '1');
    sel.addEventListener('change', function () {
      var map = { target: 'local' };
      map['edit.' + key] = sel.value;
      if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
        window.G7Core.dispatch({ handler: 'setState', params: map });
      }
    });
  }

  function ensureEditNativeSelects() {
    var card = document.querySelector('[data-cmb-company-edit]');
    if (!card) return;
    function mount(key, opts) {
      var host = card.querySelector('[data-cmb-edit-host="' + key + '"]');
      if (!host) return;
      var sel = host.querySelector('select[data-cmb-edit-free]');
      if (!sel) {
        sel = document.createElement('select');
        sel.setAttribute('data-cmb-edit-free', '1');
        sel.setAttribute('name', key);
        sel.className = 'cmb-admin-filter-native cmb-admin-edit-native';
        host.appendChild(sel);
      }
      fillSelect(sel, opts, currentEditValue(key) || sel.value);
      bindEditNative(sel, key);
    }
    mount('kind', [['company', '업체'], ['individual', '개인']]);
    mount('status', [['pending', '보류'], ['approved', '승인'], ['rejected', '거절']]);
  }

  var FORM_STATUS_OPTS = [
    ['hold', '보류'],
    ['draft', '임시저장'],
    ['request', '의뢰'],
    ['quote_request', '견적요청'],
    ['open', '견적요청(open)'],
    ['awarded', '낙찰'],
    ['disputed', '분쟁조정'],
    ['done', '완료'],
    ['cancelled', '취소']
  ];
  var FORM_AUDIENCE_OPTS = [
    ['all', '전체'],
    ['company', '업체만'],
    ['individual', '개인만'],
    ['admin', '관리자']
  ];

  function currentFormValue(key) {
    try {
      var v = g7Get('_local.form.' + key);
      if (v != null && v !== '') return String(v);
      var form = g7Get('_local.form') || {};
      if (form && form[key] != null && form[key] !== '') return String(form[key]);
    } catch (e) {}
    var el = document.querySelector('[data-cmb-job-edit] [name="' + key + '"], .cmb-admin [name="' + key + '"]');
    return el && el.value ? String(el.value) : '';
  }

  function bindFormNative(sel, key) {
    if (!sel || sel.getAttribute('data-cmb-form-bound') === '1') return;
    sel.setAttribute('data-cmb-form-bound', '1');
    sel.addEventListener('change', function () {
      unlockAdminPointer();
      var map = {};
      map['form.' + key] = sel.value;
      setLocal(map);
    });
  }

  function ensureFormNativeSelects() {
    if (!/\/admin\/maker-bids\/jobs\/\d+/.test(location.pathname || '') && !document.querySelector('[data-cmb-job-edit], [data-cmb-form-host]')) return;
    function mount(key, opts) {
      var host = document.querySelector('[data-cmb-form-host="' + key + '"]');
      if (!host) return;
      host.querySelectorAll('[role="combobox"], [data-slot="trigger"], [data-slot="select-trigger"], [aria-haspopup="listbox"]').forEach(hideVisually);
      var sel = host.querySelector('select[data-cmb-form-free]');
      if (!sel) {
        sel = document.createElement('select');
        sel.setAttribute('data-cmb-form-free', '1');
        sel.setAttribute('name', key);
        sel.className = 'cmb-admin-filter-native cmb-admin-edit-native cmb-admin-form-native';
        host.appendChild(sel);
      }
      fillSelect(sel, opts, currentFormValue(key) || sel.value);
      bindFormNative(sel, key);
    }
    mount('status', FORM_STATUS_OPTS);
    mount('audience', FORM_AUDIENCE_OPTS);
  }

  function renderCompanyEditJobTypes() {
    var host = document.querySelector('[data-cmb-company-edit] [data-cmb-job-types]');
    if (!host) return;
    var selected = [];
    try {
      var cur = g7Get('_local.edit.job_types');
      if (cur == null) {
        var ed = g7Get('_local.edit') || {};
        cur = ed.job_types || [];
      }
      if (typeof cur === 'string') {
        try { cur = JSON.parse(cur); } catch (e1) { cur = cur ? [cur] : []; }
      }
      if (Array.isArray(cur)) selected = cur.map(String);
    } catch (e2) {}
    var id = currentEditValue('id');
    function paint(opts) {
      if (!host.getAttribute('data-cmb-job-types-ready')) {
        host.innerHTML = '';
        (opts || []).forEach(function (o) {
          if (!o[0]) return;
          var label = document.createElement('label');
          label.className = 'cmb-admin-check-label';
          var box = document.createElement('input');
          box.type = 'checkbox';
          box.setAttribute('data-cmb-job-type', o[0]);
          box.name = 'job_type_' + o[0];
          var span = document.createElement('span');
          span.className = 'cmb-admin-check-text';
          span.textContent = o[1] || o[0];
          label.appendChild(box);
          label.appendChild(span);
          host.appendChild(label);
        });
        host.setAttribute('data-cmb-job-types-ready', '1');
      }
      if (host.getAttribute('data-cmb-job-types-for') === id && id) return;
      host.setAttribute('data-cmb-job-types-for', id || '');
      host.querySelectorAll('[data-cmb-job-type]').forEach(function (box) {
        box.checked = selected.indexOf(box.getAttribute('data-cmb-job-type')) >= 0;
      });
    }
    if (typeOptsCache) paint(typeOptsCache);
    else {
      paint(TYPE_FALLBACK);
      loadTypeOpts(function (opts) {
        host.removeAttribute('data-cmb-job-types-ready');
        host.removeAttribute('data-cmb-job-types-for');
        renderCompanyEditJobTypes();
      });
    }
  }

  function nestExistingIntoUploader() {
    document.querySelectorAll('.cmb-admin-uploader, [data-cmb-admin-uploader], .cmb-admin .cmb-order-uploader').forEach(function (box) {
      box.classList.add('cmb-admin-uploader-compact');
      var gallery = box.querySelector('.cmb-existing-files');
      var dashed = box.querySelector('[class*="border-dashed"]');
      if (gallery && dashed && gallery.parentElement !== dashed) {
        dashed.insertBefore(gallery, dashed.firstChild);
      }
      if (dashed) {
        var node = dashed;
        while (node && node !== box.parentElement) {
          node.style.setProperty('min-height', '0', 'important');
          node.style.setProperty('height', 'auto', 'important');
          node = node.parentElement;
        }
        dashed.style.setProperty('padding', '0.3rem 0.45rem', 'important');
      }
      box.querySelectorAll('svg').forEach(function (el) {
        if (el.closest && el.closest('.cmb-existing-files')) return;
        el.style.setProperty('display', 'none', 'important');
      });
      var seenHint = false;
      box.querySelectorAll('p').forEach(function (p) {
        if (p.closest && p.closest('.cmb-existing-files')) return;
        if (!seenHint) {
          seenHint = true;
          p.style.setProperty('margin', '0', 'important');
          p.style.setProperty('font-size', '12px', 'important');
          return;
        }
        p.style.setProperty('display', 'none', 'important');
      });
    });
  }


  function isAdminDarkChrome() {
    var html = document.documentElement;
    if (!html) return false;
    if (html.classList.contains('dark') || html.classList.contains('dark-mode') || html.classList.contains('cmb-dark-boot') || html.classList.contains('cmb-admin-dark')) return true;
    if (html.getAttribute('data-theme') === 'dark' || html.getAttribute('data-color-mode') === 'dark') return true;
    try {
      if (localStorage.getItem('cmb-theme') === 'dark' || localStorage.getItem('theme') === 'dark') return true;
    } catch (e) {}
    try {
      var el = document.querySelector('.cmb-admin') || document.body;
      if (!el) return false;
      var bg = window.getComputedStyle(el).backgroundColor || '';
      var m = bg.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
      if (m) {
        var lum = (0.2126 * +m[1] + 0.7152 * +m[2] + 0.0722 * +m[3]) / 255;
        if (lum < 0.45) return true;
      }
      // Walk parents if transparent
      var node = el.parentElement;
      for (var i = 0; i < 6 && node; i++) {
        bg = window.getComputedStyle(node).backgroundColor || '';
        m = bg.match(/rgba?\((\d+),\s*(\d+),\s*(\d+)/);
        if (m) {
          var a = bg.indexOf('rgba') === 0 ? parseFloat((bg.split(',')[3] || '1').replace(')', '')) : 1;
          if (a > 0.05) {
            lum = (0.2126 * +m[1] + 0.7152 * +m[2] + 0.0722 * +m[3]) / 255;
            if (lum < 0.45) return true;
            break;
          }
        }
        node = node.parentElement;
      }
    } catch (e2) {}
    return false;
  }

  function injectAdminDarkCss() {
    if (document.getElementById('cmb-admin-dark-css')) return;
    var s = document.createElement('style');
    s.id = 'cmb-admin-dark-css';
    /* Unconditional on admin pages — also kill host bg-white/bg-card utilities. */
    s.textContent =
      '.cmb-admin,html.cmb-admin-dark .cmb-admin,html.cmb-admin-ui .cmb-admin{color:#e5e7eb;' +
      '--cmb-admin-fg:#e5e7eb;--cmb-admin-muted:#9ca3af;' +
      '--cmb-admin-border:rgba(255,255,255,0.12);--cmb-admin-row-border:rgba(255,255,255,0.12);' +
      '--cmb-admin-card:rgba(255,255,255,0.08);--cmb-admin-row:rgba(255,255,255,0.06);' +
      '--cmb-admin-control:rgba(255,255,255,0.06);--cmb-admin-check:#e5e7eb}' +
      '.cmb-admin-card,.cmb-admin-nav,.cmb-admin-row,.cmb-admin-list-item,' +
      '.cmb-admin .cmb-section-card,.cmb-admin .cmb-list-item,' +
      '.cmb-admin-card.cmb-admin-filter,' +
      'html.cmb-admin-dark .cmb-admin-card,html.cmb-admin-dark .cmb-admin-nav,' +
      'html.cmb-admin-dark .cmb-admin-row,html.cmb-admin-dark .cmb-admin-list-item,' +
      'html.cmb-admin-dark .cmb-admin .cmb-section-card,html.cmb-admin-dark .cmb-admin .cmb-list-item,' +
      'html.cmb-admin-dark .cmb-admin-card.cmb-admin-filter,' +
      'html.cmb-admin-ui .cmb-admin-card,html.cmb-admin-ui .cmb-admin-nav,' +
      'html.cmb-admin-ui .cmb-admin-row,html.cmb-admin-ui .cmb-admin-list-item{' +
      'background:rgba(255,255,255,0.06)!important;background-color:rgba(255,255,255,0.06)!important;' +
      'border:1px solid rgba(255,255,255,0.12)!important;' +
      'border-radius:1rem;color:#e5e7eb!important}' +
      '.cmb-admin-card,.cmb-admin-nav,.cmb-admin-card.cmb-admin-filter,' +
      'html.cmb-admin-dark .cmb-admin-card,html.cmb-admin-dark .cmb-admin-nav,' +
      'html.cmb-admin-dark .cmb-admin-card.cmb-admin-filter,' +
      'html.cmb-admin-ui .cmb-admin-card,html.cmb-admin-ui .cmb-admin-nav{' +
      'background:rgba(255,255,255,0.08)!important;background-color:rgba(255,255,255,0.08)!important}' +
      '.cmb-admin-card.bg-white,.cmb-admin-card.bg-card,.cmb-admin-card.bg-background,' +
      '.cmb-admin-row.bg-white,.cmb-admin-row.bg-card,.cmb-admin-row.bg-background,' +
      '.cmb-admin-nav.bg-white,.cmb-admin-nav.bg-card,.cmb-admin-nav.bg-background,' +
      '.cmb-admin .cmb-section-card.bg-white,.cmb-admin .cmb-list-item.bg-white{' +
      'background:rgba(255,255,255,0.06)!important;background-color:rgba(255,255,255,0.06)!important}';
    (document.head || document.documentElement).appendChild(s);
  }

  function syncAdminDark() {
    var html = document.documentElement;
    if (!html) return;
    /* Admin maker-bids always uses dark translucent cards (shop chrome). */
    html.classList.add('cmb-admin-dark');
    injectAdminDarkCss();
  }

  function start() {
    if (!document.querySelector('.cmb-admin')) return;
    document.documentElement.classList.add('cmb-admin-ui');
    syncAdminDark();
    injectPortalCss();
    injectStatusPaintCss();
    enhanceSizes();
    hideLegacySizes();
    bindStatusPress();
    try { paintStatusButtons(); } catch (ePaint) {}
    ensureAdminFilterSelect();
    ensureEditNativeSelects();
    ensureFormNativeSelects();
    renderCompanyEditJobTypes();
    nestExistingIntoUploader();
    ensureCompanyEditButtons();
    ensureListBoxes();
    bindSettingsHarvest();
    bindCompanyHarvest();
    bindFilterGo();
    bindRowActions();
    if (companyIdFromEdit()) markEditingRow(companyIdFromEdit());
    paintSettingsControls();
    observeAdminStatus();
    observeAdminMenus();
    unlockAdminPointer();
    paintOpenAdminMenus();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
  setTimeout(start, 400);
  setTimeout(paintStatusButtons, 800);
  setTimeout(paintStatusButtons, 1600);
  setTimeout(ensureAdminFilterSelect, 500);
  setTimeout(ensureEditNativeSelects, 500);
  setTimeout(ensureFormNativeSelects, 550);
  setTimeout(renderCompanyEditJobTypes, 600);
  setTimeout(nestExistingIntoUploader, 800);
  setTimeout(ensureListBoxes, 800);
  setTimeout(paintSettingsControls, 900);
  setTimeout(ensureCompanyEditButtons, 600);
  setTimeout(unlockAdminPointer, 300);
  setInterval(function () {
    if (document.querySelector('.cmb-admin')) {
      try { paintStatusButtons(); } catch (ePaint2) {}
      ensureAdminFilterSelect();
      ensureEditNativeSelects();
      ensureFormNativeSelects();
      renderCompanyEditJobTypes();
      nestExistingIntoUploader();
      unlockAdminPointer();
      paintOpenAdminMenus();
      if (companyIdFromEdit()) markEditingRow(companyIdFromEdit());
    }
  }, 700);
})();
