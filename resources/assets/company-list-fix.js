(function () {
  if (window.__cmbCompanyListFix) return;
  window.__cmbCompanyListFix = true;

  function isListPage() {
    return /maker-bids\/companies/.test(location.pathname || '') || document.body.innerText.indexOf('공개 입찰자') >= 0;
  }

  function css() {
    if (document.getElementById('cmb-co-list-css')) return;
    var s = document.createElement('style');
    s.id = 'cmb-co-list-css';
    s.textContent =
      '.cmb-co-grid{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(140px,1fr))!important;gap:16px!important}' +
      '.cmb-co-card{text-align:center;padding:16px 12px!important;min-height:160px}' +
      '.cmb-co-logo{width:72px;height:72px;border-radius:9999px;object-fit:cover;display:block;margin:0 auto 10px;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.12)}';
    (document.head || document.documentElement).appendChild(s);
  }

  function attach(card, url, name) {
    if (!card || card.querySelector('.cmb-co-logo')) return;
    var img = document.createElement('img');
    img.className = 'cmb-co-logo';
    img.alt = name || '';
    img.src = url;
    img.onerror = function () { this.style.visibility = 'hidden'; };
    card.insertBefore(img, card.firstChild);
    card.classList.add('cmb-co-card');
  }

  function apply(rows) {
    var map = {};
    (rows || []).forEach(function (r) {
      if (r && r.name) map[String(r.name).replace(/\s+/g, '')] = r.logo_url || r.thumbnail_url || '';
    });
    var root = null;
    document.querySelectorAll('h2,h3,div,p').forEach(function (el) {
      if ((el.textContent || '').trim() === '공개 입찰자') root = el.parentElement;
    });
    if (root) {
      var wrap = root.querySelector(':scope > div') || root;
      wrap.classList.add('cmb-co-grid');
      Array.prototype.forEach.call(wrap.children, function (card) {
        var name = (card.textContent || '').replace(/업체|개인|승인|보류|거절/g, '').replace(/\s+/g, ' ').trim();
        var key = name.replace(/\s+/g, '');
        var url = map[key];
        if (url) attach(card, url, name);
      });
    }
    document.querySelectorAll('h2,h3,div,p').forEach(function (el) {
      if ((el.textContent || '').indexOf('내 등록') !== 0) return;
      var card = el.closest('div');
      var name = (el.textContent || '').replace(/^내 등록·\s*/, '').trim();
      var key = name.replace(/\s+/g, '');
      if (map[key] && card) attach(card, map[key], name);
    });
  }

  function run() {
    if (!isListPage()) return;
    css();
    fetch('/api/modules/custom-maker_bids/companies', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var rows = j.data || j.companies || j || [];
        if (!Array.isArray(rows) && rows.data) rows = rows.data;
        apply(Array.isArray(rows) ? rows : []);
      })
      .catch(function () {});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  setTimeout(run, 600);
})();
