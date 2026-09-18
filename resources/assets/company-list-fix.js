(function () {
  if (window.__cmbCoGridEq) return;
  window.__cmbCoGridEq = true;
  var cssText =
    '.cmb-page .cmb-co-grid,.cmb-page .cmb-card-list.cmb-co-grid,.cmb-section-card .cmb-co-grid{' +
      'display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;' +
      'width:100%!important;gap:16px!important;align-items:stretch}' +
    '.cmb-page .cmb-company-card,.cmb-page .cmb-co-card,.cmb-page .cmb-list-item.cmb-company-card{' +
      'width:100%!important;min-width:0!important;max-width:none!important;' +
      'box-sizing:border-box!important;display:flex!important;flex-direction:column!important;' +
      'align-items:center!important;text-align:center!important;' +
      'padding:18px 14px!important;border-radius:1rem!important;' +
      'border:1px solid rgba(255,255,255,.14)!important;' +
      'background:rgba(255,255,255,.07)!important;' +
      'overflow:hidden!important;word-break:keep-all!important;overflow-wrap:anywhere!important;' +
      'white-space:normal!important}' +
    '.cmb-page .cmb-company-card-title,.cmb-page .cmb-co-card p{' +
      'white-space:normal!important;overflow-wrap:anywhere!important;word-break:keep-all!important;' +
      'max-width:100%!important;margin:0}' +
    '.cmb-page .cmb-co-logo{width:72px;height:72px;border-radius:9999px;object-fit:cover;display:block;margin:0 auto 10px}' +
    '@media (max-width:1100px){.cmb-page .cmb-co-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}}' +
    '@media (max-width:700px){.cmb-page .cmb-co-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}';
  function inject() {
    var s = document.getElementById('cmb-co-grid5-css');
    if (!s) {
      s = document.createElement('style');
      s.id = 'cmb-co-grid5-css';
      (document.head || document.documentElement).appendChild(s);
    }
    s.textContent = cssText;
  }
  inject();
})();
