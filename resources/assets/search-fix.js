(function () {
  if (window.__cmbSearchFix12) return;
  window.__cmbSearchFix12 = true;

  var state = { q: '', sort: 'latest', type: '', status: '', page: 1 };
  var lastRows = [];
  var lastMeta = {};
  var selfChips = { hold: false, draft: false, disputed: false };
  var typedQ = null;

  var PUBLIC_STATUS_CHIPS = [
    ['', '전체'],
    ['pending', '승인대기'],
    ['request', '의뢰'],
    ['quote_request', '견적요청'],
    ['awarded', '낙찰'],
    ['done', '완료'],
    ['cancelled', '취소']
  ];
  var SELF_STATUS_CHIPS = [
    ['hold', '보류'],
    ['draft', '임시저장'],
    ['disputed', '분쟁조정']
  ];

  function listPath() {
    return (location.pathname || '').replace(/\/+$/, '') === '/maker-bids';
  }

  function readUrl() {
    try {
      var u = new URL(location.href);
      state.q = u.searchParams.get('q') || '';
      state.sort = u.searchParams.get('sort') || 'latest';
      state.type = u.searchParams.get('type') || '';
      state.status = u.searchParams.get('status') || '';
      state.page = parseInt(u.searchParams.get('page') || '1', 10) || 1;
    } catch (e) {}
  }

  function writeUrl(resetPage) {
    try {
      var u = new URL(location.href);
      if (resetPage) state.page = 1;
      ['q', 'sort', 'type', 'status'].forEach(function (k) {
        if (state[k]) u.searchParams.set(k, state[k]);
        else u.searchParams.delete(k);
      });
      if (!state.page || state.page <= 1) u.searchParams.delete('page');
      else u.searchParams.set('page', String(state.page));
      history.replaceState(null, '', u.pathname + u.search);
    } catch (e) {}
  }

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function hideVisually(el) {
    if (!el || el.nodeType !== 1) return;
    if (el.getAttribute('data-cmb-free') === '1' || el.getAttribute('data-cmb-sort-free') === '1' || el.getAttribute('data-cmb-native-bar') === '1') return;
    if (el.querySelector && (el.querySelector('[data-cmb-free]') || el.querySelector('[data-cmb-sort-free]') || el.querySelector('[data-cmb-native-bar]'))) return;
    el.style.setProperty('display', 'none', 'important');
    el.style.setProperty('position', 'absolute', 'important');
    el.style.setProperty('left', '-9999px', 'important');
    el.style.setProperty('width', '1px', 'important');
    el.style.setProperty('height', '1px', 'important');
    el.style.setProperty('opacity', '0', 'important');
    el.style.setProperty('overflow', 'hidden', 'important');
    el.style.setProperty('pointer-events', 'none', 'important');
    el.setAttribute('aria-hidden', 'true');
    el.tabIndex = -1;
  }

  function listBox() {
    return document.querySelector('.cmb-section-card .cmb-card-list, .cmb-card-list');
  }

  function cardHtml(item) {
    var href = '/maker-bids/' + (item.id || '');
    var badge = item.status_label || item.status || '';
    var type = item.type_name || item.type || '';
    var bids = item.bids_count != null ? item.bids_count : 0;
    var closes = item.closes_at ? '<span>마감 ' + esc(item.closes_at) + '</span>' : '';
    var aud = item.audience_label || '전체';
    var budget = item.budget_label || '미정';
    var thumb = item.thumbnail_url
      ? '<img class="cmb-list-thumb" src="' + esc(item.thumbnail_url) + '" alt="" width="64" height="64">'
      : '';
    return '<a href="' + href + '" class="cmb-job-card cmb-list-item">' +
      thumb +
      '<div class="cmb-job-card-main">' +
      '<div class="cmb-job-card-top"><p class="cmb-job-card-title">' + esc(item.title) + '</p>' +
      '<span class="cmb-badge cmb-badge-' + esc(item.status) + '">' + esc(badge) + '</span></div>' +
      '<div class="cmb-job-card-meta"><span>' + esc(type) + '</span><span>견적 ' + bids + '건</span>' + closes +
      '<span>입찰 권한 ' + esc(aud) + '</span></div>' +
      '<p class="cmb-job-card-budget">예산 ' + esc(budget) + '</p></div></a>';
  }

  function paint(rows) {
    var box = listBox();
    if (!box) return;
    lastRows = rows || [];
    var pager = box.querySelector('[data-cmb-pager]');
    var keep = pager ? pager.outerHTML : '';
    if (!lastRows.length) {
      box.innerHTML = '<p class="cmb-empty">등록된 의뢰가 없습니다.</p>' + keep;
      return;
    }
    box.innerHTML = lastRows.map(cardHtml).join('') + keep;
  }

  function updatePager(meta) {
    lastMeta = meta || {};
    var el = document.querySelector('[data-cmb-pager]');
    if (!el || !meta) return;
    el.setAttribute('data-page', String(meta.page || 1));
    el.setAttribute('data-total', String(meta.total || 0));
    el.setAttribute('data-last-page', String(meta.last_page || 1));
    el.setAttribute('data-per-page', String(meta.per_page || 10));
  }

  function chipsFromRows(rows, flags) {
    var out = {
      hold: !!(flags && flags.hold),
      draft: !!(flags && flags.draft),
      disputed: !!(flags && flags.disputed)
    };
    (rows || []).forEach(function (r) {
      if (!r) return;
      if (r.status === 'hold') out.hold = true;
      if (r.status === 'draft') out.draft = true;
      if (r.status === 'disputed') out.disputed = true;
    });
    return out;
  }

  function paintSelfChips(flags) {
    selfChips = flags || selfChips;
    var labels = { hold: '보류', draft: '임시저장', disputed: '분쟁조정' };
    document.querySelectorAll('[data-cmb-self-status]').forEach(function (a) {
      var key = a.getAttribute('data-cmb-self-status');
      var on = !!(selfChips && selfChips[key]);
      if (!(a.textContent || '').trim() && labels[key]) a.textContent = labels[key];
      if (on) {
        a.removeAttribute('hidden');
        a.classList.remove('is-hidden');
        a.style.setProperty('display', 'inline-flex', 'important');
      } else {
        a.setAttribute('hidden', '');
        a.classList.add('is-hidden');
        a.style.setProperty('display', 'none', 'important');
      }
    });
  }

  function load() {
    if (!listPath()) return;
    var p = new URLSearchParams();
    if (state.q) p.set('q', state.q);
    if (state.sort) p.set('sort', state.sort);
    if (state.type) p.set('type', state.type);
    if (state.status) p.set('status', state.status);
    p.set('page', String(state.page || 1));
    p.set('per_page', '24');
    fetch('/api/modules/custom-maker_bids/jobs?' + p.toString(), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json(); }).then(function (j) {
      var payload = j && j.data && !Array.isArray(j.data) ? j.data : j;
      var rows = Array.isArray(payload) ? payload : (payload && payload.data) || (j && j.data) || [];
      if (!Array.isArray(rows)) rows = [];
      var meta = (payload && payload.meta) || (j && j.meta) || {};
      paint(rows);
      updatePager(meta);
      paintSelfChips(chipsFromRows(rows, meta.viewer_status_chips || {}));
      markChips();
    }).catch(function () {});
  }

  function apply(resetPage) {
    rememberTyped();
    var inp = document.querySelector('[data-cmb-free]');
    if (inp) state.q = String(inp.value || '').trim();
    typedQ = state.q;
    writeUrl(resetPage !== false);
    load();
  }

  function markChips() {
    document.querySelectorAll('[data-cmb-status], [data-cmb-status-chips] a, .cmb-status-chips a, .cmb-filter-row-status a').forEach(function (a) {
      if (/^(상태|의뢰상태)$/.test((a.textContent || '').trim())) return;
      var st = a.getAttribute('data-cmb-status');
      if (st == null) {
        try { st = new URL(a.href, location.origin).searchParams.get('status') || ''; } catch (e) { st = ''; }
      }
      var on = String(st || '') === String(state.status || '');
      a.classList.toggle('is-active', on);
      if (on) a.setAttribute('aria-current', 'page');
      else a.removeAttribute('aria-current');
    });
    document.querySelectorAll('.cmb-filters a, .cmb-filters .cmb-chip, .cmb-filter-row-type a').forEach(function (a) {
      if ((a.textContent || '').trim() === '형식') return;
      var type = a.getAttribute('data-cmb-type');
      if (type == null) {
        try { type = new URL(a.href, location.origin).searchParams.get('type') || ''; } catch (e) { type = ''; }
      }
      var label = (a.textContent || '').trim();
      var on = String(type || '') === String(state.type || '');
      if (label === '전체') on = !state.type;
      a.classList.toggle('is-active', on);
      if (on) a.setAttribute('aria-current', 'page');
      else a.removeAttribute('aria-current');
    });
  }

  function rememberTyped() {
    var free = document.querySelector('[data-cmb-native-host] [data-cmb-free], [data-cmb-native-bar] [data-cmb-free]');
    if (free) typedQ = String(free.value || '');
  }

  function bindSearchSubmit(inp) {
    if (!inp || inp.getAttribute('data-cmb-enter') === '1') return;
    inp.setAttribute('data-cmb-enter', '1');
    inp.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      e.stopPropagation();
      rememberTyped();
      apply(true);
    });
  }

  function neutralizeG7(bar) {
    if (!bar) return;
    hideVisually(bar);
    bar.querySelectorAll('input, textarea, select, button, [role="combobox"], [data-slot="trigger"]').forEach(function (el) {
      hideVisually(el);
      try { el.disabled = true; } catch (e) {}
      try { el.readOnly = true; } catch (e2) {}
      el.tabIndex = -1;
      if (el.getAttribute && el.getAttribute('name') === 'q') el.removeAttribute('name');
      if (el.tagName === 'INPUT' && !state.q) el.value = '';
    });
  }

  function ensureSearch() {
    rememberTyped();
    var g7 = document.querySelector('[data-cmb-search-bar]:not([data-cmb-native-host])')
      || document.querySelector('.cmb-search-bar:not([data-cmb-native-host])');
    if (!g7 || !g7.parentNode) return;
    var host = document.querySelector('[data-cmb-native-host]');
    if (!host) {
      host = document.createElement('div');
      host.setAttribute('data-cmb-native-host', '1');
      host.className = 'cmb-section-card cmb-search-bar cmb-native-search-host mb-3';
      g7.parentNode.insertBefore(host, g7);
    }
    var native = host.querySelector('[data-cmb-native-bar]');
    if (!native) {
      native = document.createElement('div');
      native.setAttribute('data-cmb-native-bar', '1');
      native.className = 'cmb-native-search-bar';
      native.innerHTML =
        '<input type="text" data-cmb-free="1" class="cmb-search-free" placeholder="검색어" autocomplete="off" spellcheck="false">' +
        '<select data-cmb-sort-free="1" class="cmb-search-sort-free" aria-label="정렬">' +
        '<option value="latest">최신순</option><option value="created">등록순</option>' +
        '<option value="views">조회순</option><option value="status">상태순</option></select>' +
        '<button type="button" class="cmb-btn cmb-btn-primary cmb-search-go" data-cmb-search-go="1">검색</button>';
      host.appendChild(native);
      var inp0 = native.querySelector('[data-cmb-free]');
      inp0.value = typedQ != null ? typedQ : (state.q || '');
      bindSearchSubmit(inp0);
      native.querySelector('[data-cmb-sort-free]').addEventListener('change', function (e) {
        state.sort = e.target.value;
        apply(true);
      });
    }
    neutralizeG7(g7);
    var free = native.querySelector('[data-cmb-free]');
    bindSearchSubmit(free);
    if (document.activeElement !== free) {
      if (typedQ != null) {
        if (String(free.value || '') !== typedQ) free.value = typedQ;
      } else if (state.q && !String(free.value || '').trim()) {
        free.value = state.q;
      }
    }
    var sort = native.querySelector('[data-cmb-sort-free]');
    if (sort && document.activeElement !== sort) sort.value = state.sort || 'latest';
    return free;
  }

  function appendChip(row, value, label, selfKey) {
    var a = document.createElement('a');
    a.href = value ? '/maker-bids?status=' + encodeURIComponent(value) : '/maker-bids';
    a.className = selfKey ? 'cmb-chip cmb-chip-self-only' : 'cmb-chip';
    a.textContent = label;
    a.setAttribute('data-cmb-status', value);
    if (selfKey) {
      a.setAttribute('data-cmb-self-status', selfKey);
      a.hidden = true;
    }
    row.appendChild(a);
    return a;
  }

  function ensureChips() {
    var stack = document.querySelector('.cmb-filter-stack, [data-cmb-filter-stack]');
    if (!stack) {
      var bar = document.querySelector('[data-cmb-search-bar], .cmb-search-bar');
      if (!bar || !bar.parentNode) return;
      stack = document.createElement('div');
      stack.className = 'cmb-filter-stack';
      stack.setAttribute('data-cmb-filter-stack', '1');
      bar.parentNode.insertBefore(stack, bar.nextSibling);
    }
    var typeRow = document.querySelector('.cmb-filters, .cmb-filter-row-type');
    if (typeRow && typeRow.parentNode !== stack) {
      typeRow.classList.add('cmb-filter-row', 'cmb-filter-row-type');
      if (!typeRow.querySelector('.cmb-filter-label')) {
        var lab = document.createElement('span');
        lab.className = 'cmb-filter-label';
        lab.textContent = '형식';
        typeRow.insertBefore(lab, typeRow.firstChild);
      }
      stack.appendChild(typeRow);
    }
    var stRow = stack.querySelector('.cmb-filter-row-status, [data-cmb-status-chips]');
    if (!stRow) {
      stRow = document.createElement('div');
      stRow.className = 'cmb-status-chips cmb-filter-row cmb-filter-row-status';
      stRow.setAttribute('data-cmb-status-chips', '1');
      var slab = document.createElement('span');
      slab.className = 'cmb-filter-label';
      slab.textContent = '의뢰상태';
      stRow.appendChild(slab);
      PUBLIC_STATUS_CHIPS.forEach(function (it) { appendChip(stRow, it[0], it[1]); });
      SELF_STATUS_CHIPS.forEach(function (it) { appendChip(stRow, it[0], it[1], it[0]); });
      stack.appendChild(stRow);
    } else {
      SELF_STATUS_CHIPS.forEach(function (it) {
        if (stRow.querySelector('[data-cmb-self-status="' + it[0] + '"]')) return;
        appendChip(stRow, it[0], it[1], it[0]);
      });
    }
    paintSelfChips(selfChips);
  }

  function chipType(el) {
    if (!el) return null;
    if (el.getAttribute('data-cmb-type') != null) return el.getAttribute('data-cmb-type') || '';
    var href = el.getAttribute('href') || '';
    try { return new URL(el.href || href, location.origin).searchParams.get('type') || ''; } catch (e) { return ''; }
  }

  function chipStatus(el) {
    if (!el) return null;
    if (el.getAttribute('data-cmb-status') != null) return el.getAttribute('data-cmb-status') || '';
    var href = el.getAttribute('href') || '';
    try { return new URL(el.href || href, location.origin).searchParams.get('status') || ''; } catch (e) { return ''; }
  }

  document.addEventListener('click', function (e) {
    if (!listPath()) return;
    var t = e.target;
    if (!t || !t.closest) return;
    if (t.closest('[data-cmb-search-go], .cmb-search-go')) {
      e.preventDefault(); e.stopPropagation();
      apply(true);
      return;
    }
    var stEl = t.closest('[data-cmb-status], [data-cmb-status-chips] a, .cmb-status-chips a, .cmb-filter-row-status a');
    if (stEl && !/^(상태|의뢰상태)$/.test((stEl.textContent || '').trim())) {
      e.preventDefault(); e.stopPropagation();
      state.status = chipStatus(stEl) || '';
      apply(true);
      return;
    }
    var typeEl = t.closest('.cmb-filters a, .cmb-filters .cmb-chip, .cmb-filter-row-type a');
    if (typeEl && (typeEl.textContent || '').trim() !== '형식') {
      e.preventDefault(); e.stopPropagation();
      var type = chipType(typeEl);
      if ((typeEl.textContent || '').trim() === '전체') type = '';
      state.type = type || '';
      apply(true);
    }
  }, true);

  window.addEventListener('popstate', function () {
    if (!listPath()) return;
    readUrl();
    typedQ = state.q || '';
    var inp = document.querySelector('[data-cmb-free]');
    if (inp && document.activeElement !== inp) inp.value = state.q;
    load();
    markChips();
  });

  function boot() {
    if (!listPath()) return;
    readUrl();
    ensureSearch();
    ensureChips();
    markChips();
    paintSelfChips(selfChips);
    if (lastRows.length) paint(lastRows);
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  setTimeout(boot, 200);
  setTimeout(function () { boot(); load(); }, 400);
  setTimeout(boot, 1000);
  if (!window.__cmbTypedPoll11) {
    window.__cmbTypedPoll11 = setInterval(rememberTyped, 120);
  }
  if (!window.__cmbSearchObs11) {
    window.__cmbSearchObs11 = new MutationObserver(function () {
      if (!listPath()) return;
      rememberTyped();
      ensureSearch();
      ensureChips();
      if (lastRows.length) {
        var box = listBox();
        if (box && box.querySelectorAll('.cmb-job-card').length !== lastRows.length) paint(lastRows);
      }
    });
    try {
      window.__cmbSearchObs11.observe(document.documentElement, { childList: true, subtree: true });
    } catch (e) {}
  }
})();
