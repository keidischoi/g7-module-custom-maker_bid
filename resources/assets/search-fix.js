(function () {
  if (window.__cmbSearchFix2) return;
  window.__cmbSearchFix2 = true;

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

  function inputEl() {
    return document.querySelector('[data-cmb-search-free], [data-cmb-search-input], .cmb-search-input, input[name="q"]');
  }

  function detach() {
    var inp = document.querySelector('[data-cmb-search-input], .cmb-search-input, input[name="q"]');
    if (!inp || inp.getAttribute('data-cmb-search-free')) return inp;
    var clone = inp.cloneNode(true);
    clone.setAttribute('data-cmb-search-free', '1');
    clone.removeAttribute('value');
    clone.value = currentQ();
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
    btn.setAttribute('aria-label', '지우기');
    btn.textContent = '×';
    btn.style.cssText = 'position:absolute;right:8px;top:50%;transform:translateY(-50%);width:22px;height:22px;border-radius:9999px;border:0;background:rgba(127,127,127,.4);color:#fff;line-height:20px;padding:0;cursor:pointer;font-size:16px';
    wrap.appendChild(btn);
  }

  document.addEventListener('keydown', function (e) {
    var inp = e.target && e.target.closest && e.target.closest('[data-cmb-search-free], [data-cmb-search-input], input[name="q"]');
    if (!inp || e.key !== 'Enter') return;
    e.preventDefault();
    e.stopPropagation();
    go(inp.value);
  }, true);

  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('[data-cmb-search-clear]')) {
      e.preventDefault();
      e.stopPropagation();
      var inp = inputEl();
      if (inp) inp.value = '';
      go('');
      return;
    }
    if (e.target.closest && e.target.closest('[data-cmb-search-go], .cmb-search-go')) {
      e.preventDefault();
      e.stopPropagation();
      var inp2 = inputEl();
      go(inp2 ? inp2.value : '');
    }
  }, true);

  function boot() { detach(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
  setTimeout(boot, 300);
})();
