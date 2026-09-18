(function () {
  if (window.__cmbSearchFix5) return;
  window.__cmbSearchFix5 = true;

  var state = { q: '', sort: 'latest', type: '', status: '' };

  function readUrl() {
    try {
      var u = new URL(location.href);
      state.q = u.searchParams.get('q') || '';
      state.sort = u.searchParams.get('sort') || 'latest';
      state.type = u.searchParams.get('type') || '';
      state.status = u.searchParams.get('status') || '';
    } catch (e) {}
  }

  function listBox() {
    return document.querySelector('.cmb-card-list');
  }

  function writeUrl() {
    try {
      var u = new URL(location.href);
      ['q', 'sort', 'type', 'status'].forEach(function (k) {
        if (state[k]) u.searchParams.set(k, state[k]);
        else u.searchParams.delete(k);
      });
      history.replaceState(null, '', u.pathname + u.search);
    } catch (e) {}
  }

  function esc(s) {
    return String(s || '').replace(/[&<>"']/g, function (c) {
      return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
    });
  }

  function cardHtml(item) {
    var href = '/maker-bids/' + (item.id || '');
    var badge = item.status_label || item.status || '';
    var type = item.type_name || item.type || '';
    var bids = item.bids_count != null ? item.bids_count : 0;
    var closes = item.closes_at ? '<span>마감 ' + esc(item.closes_at) + '</span>' : '';
    var aud = item.audience_label || '전체';
    var budget = item.budget_label || '미정';
    return '<a href="' + href + '" class="cmb-job-card cmb-list-item">' +
      '<div class="cmb-job-card-top"><p class="cmb-job-card-title">' + esc(item.title) + '</p>' +
      '<span class="cmb-badge cmb-badge-' + esc(item.status) + '">' + esc(badge) + '</span></div>' +
      '<div class="cmb-job-card-meta"><span>' + esc(type) + '</span><span>견적 ' + bids + '건</span>' + closes +
      '<span>입찰 권한 ' + esc(aud) + '</span></div>' +
      '<p class="cmb-job-card-budget">예산 ' + esc(budget) + '</p></a>';
  }

  function paint(rows) {
    var box = listBox();
    if (!box) return;
    if (!rows || !rows.length) {
      box.innerHTML = '<p class="cmb-empty">등록된 의뢰가 없습니다.</p>';
      return;
    }
    box.innerHTML = rows.map(cardHtml).join('');
  }

  function load() {
    var p = new URLSearchParams();
    if (state.q) p.set('q', state.q);
    if (state.sort) p.set('sort', state.sort);
    if (state.type) p.set('type', state.type);
    if (state.status) p.set('status', state.status);
    p.set('per_page', '50');
    fetch('/api/modules/custom-maker_bids/jobs?' + p.toString(), {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json(); }).then(function (j) {
      var rows = j.data || j || [];
      if (rows && !Array.isArray(rows) && Array.isArray(rows.data)) rows = rows.data;
      paint(Array.isArray(rows) ? rows : []);
      markChips();
    }).catch(function () {});
  }

  function markChips() {
    document.querySelectorAll('[data-cmb-status]').forEach(function (a) {
      var on = (a.getAttribute('data-cmb-status') || '') === (state.status || '');
      a.style.background = on ? '#111827' : '';
      a.style.color = on ? '#fff' : '';
    });
    document.querySelectorAll('.cmb-filters a.cmb-chip').forEach(function (a) {
      var href = a.getAttribute('href') || '';
      var type = '';
      try { type = new URL(a.href, location.origin).searchParams.get('type') || ''; } catch (e) {}
      var on = type === (state.type || '') && href.indexOf('status=') < 0;
      if (a.textContent.trim() === '전체' && !state.type) on = true;
      if (type && type === state.type) on = true;
    });
  }

  function ensureSearch() {
    var old = document.querySelector('[data-cmb-search-input], .cmb-search-input, input[name="q"]');
    if (!old) return;
    if (old.getAttribute('data-cmb-free') === '1') return old;
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
    if (host.querySelector('[data-cmb-sort-free]')) return;
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
      writeUrl(); load();
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
    row.style.display = 'flex';
    row.style.alignItems = 'center';
    row.style.flexWrap = 'wrap';
    row.style.gap = '8px';
    var box = row.querySelector('[data-cmb-status-chips]');
    if (!box) {
      box = document.createElement('div');
      box.setAttribute('data-cmb-status-chips', '1');
      box.style.cssText = 'margin-left:auto;display:flex;flex-wrap:wrap;gap:6px';
      [['', '전체'], ['draft', '임시'], ['hold', '보류'], ['open', '입찰중'], ['awarded', '낙찰'], ['done', '완료'], ['cancelled', '취소'], ['pending', '승인대기']].forEach(function (it) {
        var a = document.createElement('a');
        a.href = '#';
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
      writeUrl(); load();
      return;
    }
    if (t.closest('[data-cmb-search-go], .cmb-search-go')) {
      e.preventDefault(); e.stopPropagation();
      var inp2 = document.querySelector('[data-cmb-free]');
      state.q = inp2 ? inp2.value.trim() : '';
      writeUrl(); load();
      return;
    }
    var st = t.closest('[data-cmb-status]');
    if (st) {
      e.preventDefault(); e.stopPropagation();
      state.status = st.getAttribute('data-cmb-status') || '';
      writeUrl(); load();
      return;
    }
    var typeA = t.closest('.cmb-filters a.cmb-chip');
    if (typeA) {
      e.preventDefault(); e.stopPropagation();
      var type = '';
      try { type = new URL(typeA.href, location.origin).searchParams.get('type') || ''; } catch (err) {}
      if ((typeA.textContent || '').trim() === '전체') type = '';
      state.type = type;
      writeUrl(); load();
    }
  }, true);

  document.addEventListener('keydown', function (e) {
    var inp = e.target && e.target.closest && e.target.closest('[data-cmb-free]');
    if (!inp || e.key !== 'Enter') return;
    e.preventDefault();
    state.q = inp.value.trim();
    writeUrl(); load();
  }, true);

  function boot() {
    readUrl();
    ensureSearch();
    ensureSort();
    ensureChips();
    load();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  setTimeout(boot, 250);
  setTimeout(boot, 900);
})();
