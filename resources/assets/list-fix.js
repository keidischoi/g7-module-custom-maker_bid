(function () {
  if (window.__cmbListFix) return;
  window.__cmbListFix = true;

  var CSS = '.cmb-job-grid{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(220px,1fr))!important;gap:16px!important;align-items:stretch}.cmb-job-grid>*{min-width:0!important;max-width:100%!important;overflow:hidden!important;word-break:keep-all}.cmb-job-grid img.cmb-thumb{width:100%;height:140px;object-fit:cover;border-radius:12px;display:block;margin-bottom:8px;background:rgba(255,255,255,.06)}.cmb-job-grid .cmb-budget{display:block;white-space:normal;overflow-wrap:anywhere}';

  function css() {
    if (document.getElementById('cmb-list-fix-css')) return;
    var s = document.createElement('style');
    s.id = 'cmb-list-fix-css';
    s.textContent = CSS;
    (document.head || document.documentElement).appendChild(s);
  }

  function labelStatus() {
    document.querySelectorAll('*').forEach(function (el) {
      if (el.children && el.children.length) return;
      var t = (el.textContent || '').trim();
      if (t === 'draft' || t === 'DRAFT') el.textContent = '임시저장';
    });
  }

  function grid() {
    var heads = Array.prototype.filter.call(document.querySelectorAll('h1,h2,h3,p,div'), function (el) {
      return /^쓰록된 의뢰/.test((el.textContent || '').trim());
    });
    heads.forEach(function (h) {
      var box = h.parentElement;
      if (!box) return;
      var cards = Array.prototype.filter.call(box.children, function (c) {
        return c !== h && (c.querySelector && (c.textContent || '').indexOf('예산') >= 0);
      });
      if (cards.length < 2) {
        var inner = box.querySelector(':scope > div');
        if (inner) {
          cards = Array.prototype.filter.call(inner.children, function (c) {
            return (c.textContent || '').indexOf('예산') >= 0;
          });
          if (cards.length) box = inner;
        }
      }
      if (!cards.length) return;
      box.classList.add('cmb-job-grid');
    });
  }

  function run() {
    css();
    grid();
    labelStatus();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  setTimeout(run, 400);
  setTimeout(run, 1200);
})();
