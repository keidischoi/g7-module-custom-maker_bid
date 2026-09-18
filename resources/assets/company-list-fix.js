(function () {
  if (window.__cmbCompanyListFix2) return;
  window.__cmbCompanyListFix2 = true;

  function isListPage() {
    return /maker-bids\/companies/.test(location.pathname || '') || (document.body && document.body.innerText.indexOf('공개 입찰자') >= 0);
  }

  function css() {
    if (document.getElementById('cmb-co-list-css')) return;
    var s = document.createElement('style');
    s.id = 'cmb-co-list-css';
    s.textContent =
      '.cmb-co-grid{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(140px,1fr))!important;gap:16px!important}' +
      '.cmb-co-card{text-align:center;padding:16px 12px!important;min-height:160px}' +
      '.cmb-co-logo,.cmb-co-fallback{width:72px;height:72px;border-radius:9999px;object-fit:cover;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;border:1px solid rgba(255,255,255,.12);font-weight:700;font-size:22px;color:#fff}' +
      '.cmb-co-logo{background:rgba(255,255,255,.08)}';
    (document.head || document.documentElement).appendChild(s);
  }

  function initialOf(name) {
    var s = String(name || '').replace(/\s+/g, '');
    if (!s) return '?';
    return s.charAt(0).toUpperCase();
  }

  function hue(name) {
    var n = 0;
    String(name || '').split('').forEach(function (c) { n = (n * 31 + c.charCodeAt(0)) >>> 0; });
    return n % 360;
  }

  function attach(card, url, name) {
    if (!card || card.querySelector('.cmb-co-logo, .cmb-co-fallback')) return;
    name = String(name || '').trim();
    if (url) {
      var img = document.createElement('img');
      img.className = 'cmb-co-logo';
      img.alt = name;
      img.src = url;
      img.onerror = function () {
        this.replaceWith(fallback(name));
      };
      card.insertBefore(img, card.firstChild);
    } else {
      card.insertBefore(fallback(name), card.firstChild);
    }
    card.classList.add('cmb-co-card');
  }

  function fallback(name) {
    var d = document.createElement('div');
    d.className = 'cmb-co-fallback';
    var h = hue(name);
    d.style.background = 'linear-gradient(135deg,hsl(' + h + ',55%,42%),hsl(' + ((h + 40) % 360) + ',55%,28%))';
    d.textContent = initialOf(name);
    return d;
  }

  function cardName(card) {
    var t = (card.textContent || '').replace(/업체|개인|승인|보류|거절|내 등록/g, ' ');
    return t.replace(/\s+/g, ' ').trim();
  }

  function apply(rows) {
    var map = {};
    (rows || []).forEach(function (r) {
      if (!r || !r.name) return;
      map[String(r.name).replace(/\s+/g, '')] = r.logo_url || r.thumbnail_url || '';
    });
    document.querySelectorAll('h2,h3,div,p').forEach(function (el) {
      var label = (el.textContent || '').trim();
      if (label !== '공개 입찰자' && label.indexOf('내 등록') !== 0) return;
      var box = el.parentElement;
      if (!box) return;
      if (label === '공개 입찰자') {
        var wrap = box.querySelector(':scope > div') || box;
        wrap.classList.add('cmb-co-grid');
        Array.prototype.forEach.call(wrap.children, function (card) {
          if (card === el) return;
          var name = cardName(card);
          if (!name || name === '공개 입찰자') return;
          attach(card, map[name.replace(/\s+/g, '')] || '', name);
        });
      } else {
        var mine = label.replace(/^내 등록·\s*/, '').trim();
        attach(box, map[mine.replace(/\s+/g, '')] || '', mine);
      }
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
      .catch(function () { apply([]); });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  setTimeout(run, 600);
})();
