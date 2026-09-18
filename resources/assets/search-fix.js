(function () {
  if (window.__cmbSearchFix6) return;
  window.__cmbSearchFix6 = true;

  var state = { q: '', sort: 'latest', type: '', status: '' };

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

  function readUrl() {
    try {
      var u = new URL(location.href);
      state.q = u.searchParams.get('q') || '';
      state.sort = u.searchParams.get('sort') || 'latest';
      state.type = u.searchParams.get('type') || '';
      state.status = u.searchParams.get('status') || '';
    } catch (e) {}
  }

  function go(resetPage) {
    try {
      var u = new URL(location.href);
      ['q', 'sort', 'type', 'status'].forEach(function (k) {
        if (state[k]) u.searchParams.set(k, state[k]);
        else u.searchParams.delete(k);
      });
      if (resetPage !== false) u.searchParams.delete('page');
      var path = u.pathname + u.search;
      if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
        window.G7Core.dispatch({ handler: 'navigate', params: { path: path } });
        return;
      }
      location.href = path;
    } catch (e) {
      location.reload();
    }
  }

  function markChips() {
    document.querySelectorAll('[data-cmb-status]').forEach(function (a) {
      var on = (a.getAttribute('data-cmb-status') || '') === (state.status || '');
      a.classList.toggle('is-active', on);
      if (on) a.setAttribute('aria-current', 'page');
      else a.removeAttribute('aria-current');
    });
    document.querySelectorAll('.cmb-filters a.cmb-chip').forEach(function (a) {
      var type = '';
      try { type = new URL(a.href, location.origin).searchParams.get('type') || ''; } catch (e) {}
      var label = (a.textContent || '').trim();
      var on = type === (state.type || '');
      if (label === '전체') on = !state.type;
      a.classList.toggle('is-active', on);
      if (on) a.setAttribute('aria-current', 'page');
      else a.removeAttribute('aria-current');
    });
  }

  function ensureSearch() {
    var old = document.querySelector('[data-cmb-search-input], .cmb-search-input, input[name="q"]');
    if (!old) return;
    if (old.getAttribute('data-cmb-free') === '1') {
      old.value = state.q;
      return old;
    }
    var wrap = old.parentElement;
    var inp = document.createElement('input');
    inp.type = 'text';
    inp.placeholder = '검색어';
    inp.className = old.className || 'cmb-search-input';
    inp.setAttribute('data-cmb-free', '1');
    inp.value = state.q;
    inp.style.paddingRight = '2rem';
    old.replaceWith(inp);
    if (wrap) {
      wrap.style.position = wrap.style.position || 'relative';
      if (!wrap.querySelector('[data-cmb-search-clear]')) {
        var x = document.createElement('button');
        x.type = 'button';
        x.setAttribute('data-cmb-search-clear', '1');
        x.textContent = '×';
        x.style.cssText = 'position:absolute;right:8px;top:50%;transform:translateY(-50%);width:22px;height:22px;border-radius:9999px;border:0;background:rgba(127,127,127,.4);color:#fff;cursor:pointer';
        wrap.appendChild(x);
      }
    }
    return inp;
  }

  function ensureSort() {
    var host = document.querySelector('[data-cmb-sort-wrap], .cmb-search-sort');
    if (!host) return;
    if (host.querySelector('[data-cmb-sort-free]')) {
      var existing = host.querySelector('[data-cmb-sort-free]');
      if (existing) existing.value = state.sort || 'latest';
      return;
    }
    Array.prototype.slice.call(host.children).forEach(function (n) { n.style.display = 'none'; });
    var sel = document.createElement('select');
    sel.setAttribute('data-cmb-sort-free', '1');
    sel.className = 'cmb-order-field rounded-lg border px-3 py-2 text-sm';
    [['latest', '최신순'], ['created', '등록순'], ['views', '조회순'], ['status', '상태순']].forEach(function (o) {
      var opt = document.createElement('option');
      opt.value = o[0]; opt.textContent = o[1];
      sel.appendChild(opt);
    });
    sel.value = state.sort || 'latest';
    sel.addEventListener('change', function () {
      state.sort = sel.value;
      go(true);
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
    row.classList.add('cmb-section-head');
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

  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t || !t.closest) return;
    if (t.closest('[data-cmb-search-clear]')) {
      e.preventDefault(); e.stopPropagation();
      var inp = document.querySelector('[data-cmb-free]');
      if (inp) inp.value = '';
      state.q = '';
      go(true);
      return;
    }
    if (t.closest('[data-cmb-search-go], .cmb-search-go')) {
      e.preventDefault(); e.stopPropagation();
      var inp2 = document.querySelector('[data-cmb-free]');
      state.q = inp2 ? inp2.value.trim() : '';
      go(true);
      return;
    }
    var st = t.closest('[data-cmb-status]');
    if (st) {
      e.preventDefault(); e.stopPropagation();
      state.status = st.getAttribute('data-cmb-status') || '';
      go(true);
      return;
    }
    var typeA = t.closest('.cmb-filters a.cmb-chip');
    if (typeA) {
      e.preventDefault(); e.stopPropagation();
      var type = '';
      try { type = new URL(typeA.href, location.origin).searchParams.get('type') || ''; } catch (err) {}
      if ((typeA.textContent || '').trim() === '전체') type = '';
      state.type = type;
      go(true);
    }
  }, true);

  document.addEventListener('keydown', function (e) {
    var inp = e.target && e.target.closest && e.target.closest('[data-cmb-free]');
    if (!inp || e.key !== 'Enter') return;
    e.preventDefault();
    state.q = inp.value.trim();
    go(true);
  }, true);

  function boot() {
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
})();
