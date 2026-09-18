(function () {
  if (window.__cmbSearchFix7) return;
  window.__cmbSearchFix7 = true;

  var state = { q: '', sort: 'latest', type: '', status: '', page: 1 };
  var typing = false;

  var STATUS_CHIPS = [
    ['', '전체'],
    ['pending', '승인대기'],
    ['hold', '보류'],
    ['request', '의뢰'],
    ['quote_request', '견적요청'],
    ['awarded', '낙찰'],
    ['done', '완료'],
    ['cancelled', '취소']
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
    var pager = box.querySelector('[data-cmb-pager]');
    var keep = pager ? pager.outerHTML : '';
    if (!rows || !rows.length) {
      box.innerHTML = '<p class="cmb-empty">등록된 의뢰가 없습니다.</p>' + keep;
      return;
    }
    box.innerHTML = rows.map(cardHtml).join('') + keep;
  }

  function updatePager(meta) {
    var el = document.querySelector('[data-cmb-pager]');
    if (!el || !meta) return;
    el.setAttribute('data-page', String(meta.page || 1));
    el.setAttribute('data-total', String(meta.total || 0));
    el.setAttribute('data-last-page', String(meta.last_page || 1));
    el.setAttribute('data-per-page', String(meta.per_page || 10));
  }

  function load() {
    if (!listPath()) return;
    var p = new URLSearchParams();
    if (state.q) p.set('q', state.q);
    if (state.sort) p.set('sort', state.sort);
    if (state.type) p.set('type', state.type);
    if (state.status) p.set('status', state.status);
    p.set('page', String(state.page || 1));
    p.set('per_page', '10');
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
      markChips();
    }).catch(function () {});
  }

  function apply(resetPage) {
    var inp = document.querySelector('[data-cmb-free]');
    if (inp) state.q = String(inp.value || '').trim();
    writeUrl(resetPage !== false);
    load();
  }

  function markChips() {
    document.querySelectorAll('[data-cmb-status], [data-cmb-status-chips] a, .cmb-status-chips a').forEach(function (a) {
      var st = a.getAttribute('data-cmb-status');
      if (st == null) {
        try { st = new URL(a.href, location.origin).searchParams.get('status') || ''; } catch (e) { st = ''; }
      }
      var on = String(st || '') === String(state.status || '');
      a.classList.toggle('is-active', on);
      if (on) a.setAttribute('aria-current', 'page');
      else a.removeAttribute('aria-current');
    });
    document.querySelectorAll('.cmb-filters a, .cmb-filters .cmb-chip').forEach(function (a) {
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

  function ensureSearch() {
    var bar = document.querySelector('[data-cmb-search-bar], .cmb-search-bar');
    if (!bar) return;
    var free = bar.querySelector('[data-cmb-free]');
    var g7 = null;
    bar.querySelectorAll('input').forEach(function (el) {
      if (el.getAttribute('data-cmb-free') === '1') return;
      if (el.getAttribute('data-cmb-search-input') || el.classList.contains('cmb-search-input') || el.getAttribute('name') === 'q') {
        g7 = el;
      }
    });
    if (free) {
      if (g7 && g7 !== free) {
        g7.setAttribute('tabindex', '-1');
        g7.setAttribute('aria-hidden', 'true');
        g7.style.display = 'none';
      }
      if (!typing && document.activeElement !== free) {
        if (state.q && !String(free.value || '').trim()) free.value = state.q;
      }
      return free;
    }
    if (!g7) return;
    var wrap = g7.parentElement || bar;
    var inp = document.createElement('input');
    inp.type = 'text';
    inp.placeholder = g7.getAttribute('placeholder') || '검색어';
    inp.className = (g7.className || 'cmb-search-input') + ' cmb-search-free';
    inp.setAttribute('data-cmb-free', '1');
    inp.setAttribute('autocomplete', 'off');
    inp.value = String(g7.value || state.q || '');
    wrap.style.position = wrap.style.position || 'relative';
    wrap.style.flex = '1 1 12rem';
    g7.setAttribute('tabindex', '-1');
    g7.setAttribute('aria-hidden', 'true');
    g7.style.display = 'none';
    g7.parentNode.insertBefore(inp, g7);
    inp.addEventListener('focus', function () { typing = true; });
    inp.addEventListener('blur', function () { typing = false; });
    inp.addEventListener('input', function () { typing = true; state.q = inp.value; });
    if (!wrap.querySelector('[data-cmb-search-clear]')) {
      var x = document.createElement('button');
      x.type = 'button';
      x.setAttribute('data-cmb-search-clear', '1');
      x.textContent = '×';
      x.style.cssText = 'position:absolute;right:8px;top:50%;transform:translateY(-50%);width:22px;height:22px;border-radius:9999px;border:0;background:rgba(127,127,127,.4);color:#fff;cursor:pointer;z-index:2';
      wrap.appendChild(x);
    }
    return inp;
  }

  function ensureSort() {
    var host = document.querySelector('[data-cmb-sort-wrap], .cmb-search-sort');
    if (!host) return;
    Array.prototype.slice.call(host.children).forEach(function (n) {
      if (n.getAttribute && n.getAttribute('data-cmb-sort-free') === '1') return;
      n.style.display = 'none';
      n.setAttribute('aria-hidden', 'true');
    });
    var existing = host.querySelector('[data-cmb-sort-free]');
    if (existing) {
      if (document.activeElement !== existing) existing.value = state.sort || 'latest';
      return;
    }
    var sel = document.createElement('select');
    sel.setAttribute('data-cmb-sort-free', '1');
    sel.setAttribute('aria-label', '정렬');
    sel.className = 'cmb-order-field cmb-search-sort-free rounded-lg border px-3 py-2 text-sm';
    [['latest', '최신순'], ['created', '등록순'], ['views', '조회순'], ['status', '상태순']].forEach(function (o) {
      var opt = document.createElement('option');
      opt.value = o[0];
      opt.textContent = o[1];
      sel.appendChild(opt);
    });
    sel.value = state.sort || 'latest';
    sel.addEventListener('change', function () {
      state.sort = sel.value;
      apply(true);
    });
    host.appendChild(sel);
  }

  function ensureChips() {
    var heads = document.querySelectorAll('.cmb-section-title, h2');
    var title = null;
    heads.forEach(function (h) { if ((h.textContent || '').trim() === '등록된 의뢰') title = h; });
    if (!title) return;
    var row = title.parentElement;
    if (!row) return;
    var box = row.querySelector('[data-cmb-status-chips]');
    if (!box) {
      box = document.createElement('div');
      box.setAttribute('data-cmb-status-chips', '1');
      box.className = 'cmb-status-chips';
      STATUS_CHIPS.forEach(function (it) {
        var a = document.createElement('a');
        a.href = it[0] ? '/maker-bids?status=' + encodeURIComponent(it[0]) : '/maker-bids';
        a.className = 'cmb-chip';
        a.textContent = it[1];
        a.setAttribute('data-cmb-status', it[0]);
        box.appendChild(a);
      });
      row.appendChild(box);
    }
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
    if (t.closest('[data-cmb-search-clear]')) {
      e.preventDefault(); e.stopPropagation();
      var inp = document.querySelector('[data-cmb-free]');
      if (inp) inp.value = '';
      state.q = '';
      typing = false;
      apply(true);
      return;
    }
    if (t.closest('[data-cmb-search-go], .cmb-search-go')) {
      e.preventDefault(); e.stopPropagation();
      typing = false;
      apply(true);
      return;
    }
    var stEl = t.closest('[data-cmb-status], [data-cmb-status-chips] a, .cmb-status-chips a');
    if (stEl) {
      e.preventDefault(); e.stopPropagation();
      state.status = chipStatus(stEl) || '';
      apply(true);
      return;
    }
    var typeEl = t.closest('.cmb-filters a, .cmb-filters .cmb-chip');
    if (typeEl) {
      e.preventDefault(); e.stopPropagation();
      var type = chipType(typeEl);
      if ((typeEl.textContent || '').trim() === '전체') type = '';
      state.type = type || '';
      apply(true);
    }
  }, true);

  document.addEventListener('keydown', function (e) {
    var inp = e.target && e.target.closest && e.target.closest('[data-cmb-free]');
    if (!inp) return;
    if (e.key === 'Enter') {
      e.preventDefault();
      typing = false;
      apply(true);
    }
  }, true);

  window.addEventListener('popstate', function () {
    if (!listPath()) return;
    readUrl();
    var inp = document.querySelector('[data-cmb-free]');
    if (inp && document.activeElement !== inp) inp.value = state.q;
    load();
    markChips();
  });

  function boot() {
    if (!listPath()) return;
    readUrl();
    ensureSearch();
    ensureSort();
    ensureChips();
    markChips();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  setTimeout(boot, 250);
  setTimeout(boot, 900);
  if (!window.__cmbSearchObs7) {
    window.__cmbSearchObs7 = new MutationObserver(function () {
      if (!listPath()) return;
      ensureSearch();
      ensureSort();
      ensureChips();
    });
    try {
      window.__cmbSearchObs7.observe(document.documentElement, { childList: true, subtree: true });
    } catch (e) {}
  }
})();
