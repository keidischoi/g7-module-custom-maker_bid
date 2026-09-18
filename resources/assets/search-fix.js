(function () {
  if (window.__cmbSearchFix) return;
  window.__cmbSearchFix = true;

  function inputEl() {
    return document.querySelector('[data-cmb-search-input], .cmb-search-input, input[name="q"]');
  }

  function currentQ() {
    try { return new URL(location.href).searchParams.get('q') || ''; } catch (e) { return ''; }
  }

  function go(q) {
    var url = new URL(location.href);
    q = String(q || '').trim();
    if (q) url.searchParams.set('q', q);
    else url.searchParams.delete('q');
    url.searchParams.set('page', '1');
    var sortEl = document.querySelector('[data-cmb-sort-select], select[name="sort"]');
    if (sortEl && sortEl.value) url.searchParams.set('sort', sortEl.value);
    location.href = url.pathname + url.search;
  }

  function ensureClear(inp) {
    if (!inp || inp.getAttribute('data-cmb-clear-ready')) return;
    inp.setAttribute('data-cmb-clear-ready', '1');
    var wrap = inp.parentElement;
    if (!wrap) return;
    wrap.style.position = wrap.style.position || 'relative';
    if (wrap.querySelector('[data-cmb-search-clear]')) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.setAttribute('data-cmb-search-clear', '1');
    btn.setAttribute('aria-label', '지우기');
    btn.textContent = '×';
    btn.style.cssText = 'position:absolute;right:8px;top:50%;transform:translateY(-50%);width:22px;height:22px;border-radius:9999px;border:0;background:rgba(127,127,127,.35);color:#fff;line-height:22px;padding:0;cursor:pointer;font-size:14px';
    wrap.appendChild(btn);
    inp.style.paddingRight = '2rem';
  }

  document.addEventListener('keydown', function (e) {
    var inp = e.target && e.target.closest && e.target.closest('[data-cmb-search-input], .cmb-search-input, input[name="q"]');
    if (!inp || e.key !== 'Enter') return;
    e.preventDefault();
    e.stopPropagation();
    go(inp.value);
  }, true);

  document.addEventListener('click', function (e) {
    var clear = e.target && e.target.closest && e.target.closest('[data-cmb-search-clear]');
    if (clear) {
      e.preventDefault();
      e.stopPropagation();
      var inp = inputEl();
      if (inp) inp.value = '';
      go('');
      return;
    }
    var btn = e.target && e.target.closest && e.target.closest('[data-cmb-search-go], .cmb-search-go');
    if (!btn) return;
    e.preventDefault();
    e.stopPropagation();
    var inp2 = inputEl();
    go(inp2 ? inp2.value : '');
  }, true);

  function boot() {
    var inp = inputEl();
    if (!inp) return;
    ensureClear(inp);
    if (!inp.getAttribute('data-cmb-q-init') && !inp.value) {
      inp.value = currentQ();
      inp.setAttribute('data-cmb-q-init', '1');
    }
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  setTimeout(boot, 400);
})();
