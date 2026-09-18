(function () {
  if (window.__cmbSearchFix4) return;
  window.__cmbSearchFix4 = true;

  function qs(name) {
    try { return new URL(location.href).searchParams.get(name) || ''; } catch (e) { return ''; }
  }

  function go(extra) {
    extra = extra || {};
    var url = new URL(location.href);
    Object.keys(extra).forEach(function (k) {
      if (extra[k] === '' || extra[k] == null) url.searchParams.delete(k);
      else url.searchParams.set(k, extra[k]);
    });
    url.searchParams.set('page', '1');
    location.href = url.pathname + url.search;
  }

  function inputEl() {
    return document.querySelector('[data-cmb-search-free], [data-cmb-search-input], .cmb-search-input, input[name="q"]');
  }

  function addStatusChips() {
    var heads = document.querySelectorAll('.cmb-section-title, h2');
    var target = null;
    heads.forEach(function (h) {
      if ((h.textContent || '').trim() === '등록된 의뢰') target = h;
    });
    if (!target) return;
    var row = target.parentElement;
    if (!row) return;
    if (row.querySelector('[data-cmb-status-chips]')) return;
    row.style.display = 'flex';
    row.style.alignItems = 'center';
    row.style.flexWrap = 'wrap';
    row.style.gap = '8px';
    var box = document.createElement('div');
    box.setAttribute('data-cmb-status-chips', '1');
    box.style.cssText = 'margin-left:auto;display:flex;flex-wrap:wrap;gap:6px';
    var items = [
      ['', '전체'],
      ['draft', '임시'],
      ['hold', '보류'],
      ['open', '입찰중'],
      ['awarded', '낙찰'],
      ['done', '완료'],
      ['cancelled', '취소'],
      ['pending', '승인대기']
    ];
    var cur = qs('status');
    items.forEach(function (it) {
      var a = document.createElement('a');
      a.href = '#';
      a.textContent = it[1];
      a.setAttribute('data-cmb-status', it[0]);
      a.className = 'cmb-chip';
      if (cur === it[0] || (!cur && it[0] === '')) {
        a.style.background = '#111827';
        a.style.color = '#fff';
      }
      a.addEventListener('click', function (e) {
        e.preventDefault();
        go({ status: it[0] });
      });
      box.appendChild(a);
    });
    row.appendChild(box);
  }

  function addStatusOption() {
    var sel = document.querySelector('[data-cmb-sort-select], select[name="sort"]');
    if (!sel) return;
    var exists = false;
    Array.prototype.forEach.call(sel.options || [], function (o) { if (o.value === 'status') exists = true; });
    if (!exists) {
      var opt = document.createElement('option');
      opt.value = 'status';
      opt.textContent = '상태순';
      sel.appendChild(opt);
    }
    var cur = qs('sort');
    if (cur) sel.value = cur;
    if (!sel.getAttribute('data-cmb-sort-bound')) {
      sel.setAttribute('data-cmb-sort-bound', '1');
      sel.addEventListener('change', function () {
        var inp = inputEl();
        go({ q: inp ? inp.value : qs('q'), sort: sel.value });
      });
    }
  }

  function detach() {
    var inp = document.querySelector('[data-cmb-search-input], .cmb-search-input, input[name="q"]');
    if (!inp || inp.getAttribute('data-cmb-search-free')) return inp;
    var clone = inp.cloneNode(true);
    clone.setAttribute('data-cmb-search-free', '1');
    clone.removeAttribute('value');
    clone.value = qs('q');
    inp.parentNode.replaceChild(clone, inp);
    ensureClear(clone);
    return clone;
  }

  function ensureClear(inp) {
    if (!inp) return;
    var wrap = inp.parentElement;
    if (!wrap || wrap.querySelector('[data-cmb-search-clear]')) return;
    wrap.style.position = wrap.style.position || 'relative';
    inp.style.paddingRight = '2rem';
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.setAttribute('data-cmb-search-clear', '1');
    btn.textContent = '×';
    btn.style.cssText = 'position:absolute;right:8px;top:50%;transform:translateY(-50%);width:22px;height:22px;border-radius:9999px;border:0;background:rgba(127,127,127,.4);color:#fff;cursor:pointer';
    wrap.appendChild(btn);
  }

  document.addEventListener('keydown', function (e) {
    var inp = e.target && e.target.closest && e.target.closest('[data-cmb-search-free], input[name="q"]');
    if (!inp || e.key !== 'Enter') return;
    e.preventDefault();
    var sel = document.querySelector('[data-cmb-sort-select], select[name="sort"]');
    go({ q: inp.value, sort: sel ? sel.value : qs('sort') });
  }, true);

  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-cmb-search-clear]')) {
      e.preventDefault();
      var inp = inputEl();
      if (inp) inp.value = '';
      go({ q: '' });
      return;
    }
    if (e.target.closest && e.target.closest('[data-cmb-search-go], .cmb-search-go')) {
      e.preventDefault();
      var inp2 = inputEl();
      var sel2 = document.querySelector('[data-cmb-sort-select], select[name="sort"]');
      go({ q: inp2 ? inp2.value : '', sort: sel2 ? sel2.value : qs('sort') });
    }
  }, true);

  function boot() { detach(); addStatusOption(); addStatusChips(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  setTimeout(boot, 300);
})();
