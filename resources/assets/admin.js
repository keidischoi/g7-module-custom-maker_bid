(function () {
  if (window.__cmbAdminJs26) return;
  window.__cmbAdminJs26 = true;
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
      if (k && out[k].indexOf(b) < 0) out[k].push(b);
    });
    return out;
  }

  function paintGroup(root, st, entity) {
    var btns = buttonsIn(root);
    btns.approve.forEach(function (b) { paintBtn(b, isApproveOn(st), 'approve'); });
    btns.hold.forEach(function (b) { paintBtn(b, isHoldOn(st, entity), 'hold'); });
    btns.reject.forEach(function (b) { paintBtn(b, st === 'rejected', 'reject'); });
  }

  function companyIdFromEdit() {
    var h = document.body.innerText.match(/선택 업체 관리 \(#(\d+)/);
    return h ? h[1] : '';
  }

  function postCompany(id, action) {
    if (!id) return;
    fetch('/api/modules/custom-maker_bids/admin/companies/' + id + '/' + action, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function () { location.reload(); });
  }

  function ensureCompanyEditButtons() {
    var save = null;
    document.querySelectorAll('button').forEach(function (b) {
      if ((b.textContent || '').trim() === '선택 업체 저장') save = b;
    });
    if (!save || save.getAttribute('data-cmb-co-btns')) return;
    save.setAttribute('data-cmb-co-btns', '1');
    var wrap = save.parentElement || save;
    function add(label, action, confirmMsg) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = label;
      var kind = label === '승인' ? 'cmb-btn-approve' : label === '보류' ? 'cmb-btn-hold' : label === '거절' ? 'cmb-btn-reject' : '';
      b.className = (kind ? kind + ' ' : '') + 'px-3 py-1.5 text-sm rounded-lg border mr-2';
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
    if (el.getAttribute && (el.getAttribute('data-cmb-filter-free') === '1' || el.getAttribute('data-cmb-filter-wrap') === '1')) return;
    if (el.closest && (el.closest('[data-cmb-filter-free]') || el.closest('[data-cmb-filter-wrap]'))) return;
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

  function currentFilterStatus() {
    try {
      if (window.G7Core && window.G7Core.state && window.G7Core.state.get) {
        return String((window.G7Core.state.get('_local.filter.status')) || '');
      }
    } catch (e) {}
    return '';
  }

  function bindNativeFilter(sel, company) {
    if (sel.getAttribute('data-cmb-filter-bound') === '1') return;
    sel.setAttribute('data-cmb-filter-bound', '1');
    sel.addEventListener('change', function () {
      if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
        window.G7Core.dispatch({ handler: 'setState', params: { target: 'local', 'filter.status': sel.value } });
        window.G7Core.dispatch({ handler: 'refetchDataSource', params: { dataSourceId: filterDsId(company) } });
      }
    });
  }

  function ensureAdminFilterSelect() {
    document.querySelectorAll('.cmb-admin-filter').forEach(function (bar) {
      var company = (bar.getAttribute('data-cmb-filter-entity') === 'company') ||
        (bar.getAttribute('data-cmb-filter-entity') !== 'job' && isCompanyAdminPage());
      var opts = company
        ? [['', '전체'], ['pending', '보류'], ['approved', '승인'], ['rejected', '거절']]
        : [['', '전체 상태'], ['hold', '보류'], ['draft', '임시저장'], ['request', '의뢰'], ['quote_request', '견적요청(open)'], ['awarded', '낙찰'], ['disputed', '분쟁조정'], ['done', '완료'], ['cancelled', '취소']];
      bar.querySelectorAll('.cmb-admin-filter-status, .cmb-admin-select-host').forEach(function (host) {
        if (host.getAttribute('data-cmb-filter-wrap') === '1') return;
        hideVisually(host);
      });
      bar.querySelectorAll('[role="combobox"], [data-slot="trigger"], [data-slot="select-trigger"]').forEach(function (el) {
        if (el.closest && el.closest('[data-cmb-filter-wrap]')) return;
        hideVisually(el);
      });
      var wrap = bar.querySelector('[data-cmb-filter-wrap]');
      if (!wrap) {
        wrap = document.createElement('div');
        wrap.setAttribute('data-cmb-filter-wrap', '1');
        wrap.className = 'cmb-admin-filter-native-wrap';
        var lab = document.createElement('p');
        lab.className = 'cmb-admin-label';
        lab.textContent = '상태';
        var sel = document.createElement('select');
        sel.setAttribute('data-cmb-filter-free', '1');
        sel.setAttribute('name', 'status');
        sel.className = 'cmb-admin-filter-native';
        opts.forEach(function (o) {
          var op = document.createElement('option');
          op.value = o[0];
          op.textContent = o[1];
          sel.appendChild(op);
        });
        var cur = currentFilterStatus();
        if (cur) sel.value = cur;
        wrap.appendChild(lab);
        wrap.appendChild(sel);
        var go = bar.querySelector('.cmb-admin-filter-go');
        bar.insertBefore(wrap, go || bar.firstChild);
      }
      var native = wrap.querySelector('[data-cmb-filter-free]');
      bindNativeFilter(native, company);
    });
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
      'width:12rem!important;min-width:12rem!important;max-width:14rem!important;height:2.25rem!important;opacity:1!important;pointer-events:auto!important}';
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

  function injectPortalCss() {
    if (document.getElementById('cmb-admin-select-portal-css')) return;
    var s = document.createElement('style');
    s.id = 'cmb-admin-select-portal-css';
    s.textContent =
      'html.cmb-admin-ui [role="listbox"],html.cmb-admin-ui [data-slot="select-content"],html.cmb-admin-ui [data-radix-select-content],html.cmb-admin-ui [data-radix-popper-content-wrapper]{width:max-content!important;min-width:14rem!important;max-width:min(90vw,40rem)!important;white-space:nowrap!important;word-break:keep-all!important;overflow-wrap:normal!important;box-sizing:border-box!important;}' +
      'html.cmb-admin-ui [role="option"],html.cmb-admin-ui [data-slot="select-item"]{white-space:nowrap!important;word-break:keep-all!important;overflow-wrap:normal!important;width:auto!important;min-width:100%!important;display:flex!important;flex-direction:row!important;align-items:center!important;}';
    (document.head || document.documentElement).appendChild(s);
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
    var root = document.querySelector('.cmb-admin');
    if (!root) return {};
    var map = {};
    var selectKeys = { type: 1, status: 1, audience: 1, kind: 1, nav_insert: 1, bid_allow: 1, default_job_status: 1 };
    var cur = {};
    try {
      if (window.G7Core && window.G7Core.state && typeof window.G7Core.state.get === 'function') {
        cur = window.G7Core.state.get('_local.' + prefix) || {};
      }
    } catch (e) { cur = {}; }
    root.querySelectorAll('[name]').forEach(function (el) {
      var name = el.getAttribute('name');
      if (!name || el.type === 'file') return;
      if (el.type === 'checkbox') {
        if (cur && typeof cur[name] === 'boolean') map[prefix + '.' + name] = cur[name];
        else map[prefix + '.' + name] = !!el.checked;
        return;
      }
      var tag = (el.tagName || '').toUpperCase();
      if (tag !== 'INPUT' && tag !== 'TEXTAREA' && tag !== 'SELECT') return;
      var val = el.value;
      if (selectKeys[name] && String(val || '').trim() === '') return;
      // Empty DOM must not zero partial admin edits (logo / image upload saves).
      if (String(val || '').trim() === '') return;
      map[prefix + '.' + name] = val;
    });
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
      var go = e.target && e.target.closest ? e.target.closest('.cmb-admin-filter-go, .cmb-admin-filter > button') : null;
      if (!go || (go.textContent || '').indexOf('필터') < 0) return;
      var sel = document.querySelector('.cmb-admin-filter [data-cmb-filter-free]');
      if (!sel) return;
      if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
        window.G7Core.dispatch({ handler: 'setState', params: { target: 'local', 'filter.status': sel.value } });
      }
    }, true);
  }

  function bindCompanyHarvest() {
    if (document.documentElement.getAttribute('data-cmb-co-harvest')) return;
    if (!/\/admin\/maker-bids\/companies/.test(location.pathname || '')) return;
    document.documentElement.setAttribute('data-cmb-co-harvest', '1');
    document.addEventListener(
      'click',
      function (e) {
        var btn = e.target && e.target.closest ? e.target.closest('button') : null;
        if (!btn) return;
        var label = (btn.textContent || '').replace(/\s+/g, ' ').trim();
        if (label.indexOf('선택 업체 저장') === -1) return;
        var map = harvestNamedInto('edit');
        if (Object.keys(map).length) setLocal(map);
      },
      true
    );
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
    paintStatusButtons();
    ensureAdminFilterSelect();
    ensureCompanyEditButtons();
    ensureListBoxes();
    bindSettingsHarvest();
    bindCompanyHarvest();
    bindFilterGo();
    paintSettingsControls();
    observeAdminStatus();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
  setTimeout(start, 400);
  setTimeout(paintStatusButtons, 800);
  setTimeout(paintStatusButtons, 1600);
  setTimeout(ensureAdminFilterSelect, 500);
  setTimeout(ensureListBoxes, 800);
  setTimeout(paintSettingsControls, 900);
  setTimeout(ensureCompanyEditButtons, 600);
  setInterval(function () {
    if (document.querySelector('.cmb-admin')) {
      paintStatusButtons();
      ensureAdminFilterSelect();
    }
  }, 700);
})();
