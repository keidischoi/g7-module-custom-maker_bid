(function () {
  if (window.__cmbAdminJs35) return;
  window.__cmbAdminJs35 = true;
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
    if (el.getAttribute && (el.getAttribute('data-cmb-filter-free') === '1' || el.getAttribute('data-cmb-filter-wrap') || el.getAttribute('data-cmb-filter-host'))) return;
    if (el.closest && (el.closest('[data-cmb-filter-free]') || el.closest('select[data-cmb-filter-free]'))) return;
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
      'html.cmb-admin-ui,html.cmb-admin-ui body,html.cmb-admin-ui body[data-scroll-locked]{pointer-events:auto!important}' +
      'html.cmb-admin-ui [data-state="closed"][data-radix-popper-content-wrapper],' +
      'html.cmb-admin-ui [data-state="closed"][data-radix-select-content],' +
      'html.cmb-admin-ui [data-state="closed"][role="listbox"]{display:none!important;pointer-events:none!important;visibility:hidden!important}' +
      'html.cmb-admin-ui [role="listbox"],html.cmb-admin-ui [data-slot="select-content"],html.cmb-admin-ui [data-radix-select-content],' +
      'html.cmb-admin-ui [data-radix-popper-content-wrapper]:not([data-state="closed"]){' +
      'background:#1e293b!important;color:#f8fafc!important;border:1px solid rgba(255,255,255,0.2)!important;' +
      'box-shadow:0 12px 40px rgba(0,0,0,0.5)!important;z-index:2147483000!important;opacity:1!important;' +
      'pointer-events:auto!important;width:max-content!important;min-width:12rem!important;max-width:min(90vw,28rem)!important;' +
      'white-space:nowrap!important;word-break:keep-all!important;overflow:auto!important;box-sizing:border-box!important}' +
      'html.cmb-admin-ui [role="option"],html.cmb-admin-ui [data-slot="select-item"]{' +
      'color:#f8fafc!important;background:transparent!important;white-space:nowrap!important;word-break:keep-all!important;' +
      'width:auto!important;min-width:100%!important;display:flex!important;flex-direction:row!important;align-items:center!important;' +
      'padding:0.4rem 0.75rem!important}' +
      'html.cmb-admin-ui [role="option"][data-highlighted],html.cmb-admin-ui [role="option"]:hover,' +
      'html.cmb-admin-ui [data-slot="select-item"][data-highlighted]{background:rgba(255,255,255,0.12)!important;color:#fff!important}' +
      '.cmb-admin-row-actions,.cmb-admin-row-actions a,.cmb-admin-row-actions button{position:relative;z-index:2;pointer-events:auto!important}';
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

  function adminFetch(url, method, okMsg) {
    var headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    var token = csrfToken();
    if (token) {
      headers['X-CSRF-TOKEN'] = token;
      headers['X-XSRF-TOKEN'] = token;
    }
    return fetch(url, { method: method || 'POST', credentials: 'same-origin', headers: headers }).then(function (res) {
      if (!res.ok) {
        return res.json().catch(function () { return {}; }).then(function (j) {
          var msg = (j && (j.message || (j.error && j.error.message))) || '처리에 실패했습니다.';
          if (window.G7Core && window.G7Core.dispatch) {
            window.G7Core.dispatch({ handler: 'toast', params: { type: 'error', message: msg } });
          }
          throw new Error(msg);
        });
      }
      if (window.G7Core && window.G7Core.dispatch) {
        if (okMsg) window.G7Core.dispatch({ handler: 'toast', params: { type: 'success', message: okMsg } });
        window.G7Core.dispatch({ handler: 'refetchDataSource', params: { dataSourceId: 'jobs' } });
        window.G7Core.dispatch({ handler: 'refetchDataSource', params: { dataSourceId: 'companies' } });
      } else {
        location.reload();
      }
    });
  }

  function bindRowActions() {
    if (document.documentElement.getAttribute('data-cmb-row-actions')) return;
    document.documentElement.setAttribute('data-cmb-row-actions', '1');
    document.addEventListener('pointerdown', function () { unlockAdminPointer(); }, true);
    document.addEventListener('click', function (e) {
      unlockAdminPointer();
      var t = e.target;
      if (!t || !t.closest) return;
      var row = t.closest('.cmb-admin-row');
      if (!row || !row.closest('.cmb-admin')) return;
      if (t.closest('a')) return;
      var btn = t.closest('button, [role="button"]');
      if (!btn || !row.contains(btn)) return;
      var label = (btn.textContent || '').replace(/\s+/g, ' ').trim();
      var kind = kindFromEl(btn);
      var entity = inferEntity(row, statusFromRow(row));
      var id = idFromRow(row);
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
      } else if (/^취소/.test(label) && entity !== 'company') {
        action = 'cancel';
        confirmMsg = '취소할까요?';
        okMsg = '취소했습니다.';
      } else if (/^삭제/.test(label)) {
        action = '';
        method = 'DELETE';
        confirmMsg = '삭제할까요?';
        okMsg = '삭제했습니다.';
      } else {
        return;
      }
      e.preventDefault();
      e.stopPropagation();
      if (typeof e.stopImmediatePropagation === 'function') e.stopImmediatePropagation();
      if (!confirm(confirmMsg)) return;
      adminFetch(base + encodeURIComponent(id) + (action ? '/' + action : ''), method, okMsg);
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
    bindRowActions();
    paintSettingsControls();
    observeAdminStatus();
    unlockAdminPointer();
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
  setTimeout(unlockAdminPointer, 300);
  setInterval(function () {
    if (document.querySelector('.cmb-admin')) {
      paintStatusButtons();
      ensureAdminFilterSelect();
      unlockAdminPointer();
    }
  }, 700);
})();
