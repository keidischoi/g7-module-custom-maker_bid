(function () {
  if (window.__cmbCoGrid5) return;
  window.__cmbCoGrid5 = true;
  function css() {
    if (document.getElementById('cmb-co-grid5-css')) return;
    var s = document.createElement('style');
    s.id = 'cmb-co-grid5-css';
    s.textContent =
      '.cmb-co-grid,.cmb-card-list.cmb-co-grid{display:grid!important;grid-template-columns:repeat(5,minmax(0,1fr))!important;gap:16px!important}' +
      '@media (max-width:1100px){.cmb-co-grid,.cmb-card-list.cmb-co-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}}' +
      '@media (max-width:700px){.cmb-co-grid,.cmb-card-list.cmb-co-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}' +
      '.cmb-co-logo{width:72px;height:72px;border-radius:9999px;object-fit:cover;display:block;margin:0 auto 10px;border:1px solid rgba(255,255,255,.12)}' +
      '.cmb-co-card,.cmb-company-card{text-align:center;padding:16px 12px}';
    (document.head || document.documentElement).appendChild(s);
  }
  function run() { css(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
})();
