(function () {
  if (window.__cmbListFix3) return;
  window.__cmbListFix3 = true;

  function css() {
    if (document.getElementById('cmb-list-fix-css')) return;
    var s = document.createElement('style');
    s.id = 'cmb-list-fix-css';
    s.textContent =
      '.cmb-job-grid{display:grid!important;grid-template-columns:repeat(auto-fill,minmax(230px,1fr))!important;gap:16px!important;align-items:stretch}' +
      '.cmb-job-grid>*{min-width:0!important;max-width:100%!important;overflow:hidden!important;word-break:keep-all;white-space:normal!important}' +
      '.cmb-job-grid img.cmb-thumb{width:100%;height:140px;object-fit:cover;border-radius:12px;display:block;margin:0 0 10px;background:rgba(255,255,255,.06)}';
    (document.head || document.documentElement).appendChild(s);
  }

  function looksJob(el) {
    var t = el.textContent || '';
    return t.indexOf('예산') >= 0 && (t.indexOf('견적') >= 0 || t.indexOf('마감') >= 0) && t.length < 800;
  }

  function grid() {
    document.querySelectorAll('div').forEach(function (div) {
      var kids = Array.prototype.slice.call(div.children || []);
      var cards = kids.filter(looksJob);
      if (cards.length < 2) return;
      div.classList.add('cmb-job-grid');
      div.style.display = 'grid';
    });
  }

  function relabel() {
    document.querySelectorAll('span,div,p,button').forEach(function (el) {
      if (el.children && el.children.length) return;
      var t = (el.textContent || '').trim();
      if (t === 'draft' || t === 'DRAFT') el.textContent = '임시저장';
    });
  }

  function thumbs(rows) {
    var byTitle = {};
    (rows || []).forEach(function (r) {
      if (!r || !r.title) return;
      var img = r.image_url || (r.images && r.images[0] && (r.images[0].thumbnail_url || r.images[0].url || r.images[0].download_url));
      if (img) byTitle[String(r.title).replace(/\s+/g, '')] = img;
    });
    document.querySelectorAll('.cmb-job-grid > *').forEach(function (card) {
      if (card.querySelector('img.cmb-thumb')) return;
      var key = (card.textContent || '').split('
')[0].replace(/\s+/g, '');
      var url = null;
      Object.keys(byTitle).forEach(function (t) {
        if (!url && key.indexOf(t) >= 0) url = byTitle[t];
      });
      if (!url) return;
      var img = document.createElement('img');
      img.className = 'cmb-thumb';
      img.src = url;
      img.alt = '';
      card.insertBefore(img, card.firstChild);
    });
  }

  function run() {
    css();
    grid();
    relabel();
    fetch('/api/modules/custom-maker_bids/jobs', { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var rows = j.data || j || [];
        if (!Array.isArray(rows) && rows.data) rows = rows.data;
        thumbs(Array.isArray(rows) ? rows : []);
      })
      .catch(function () {});
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  setTimeout(run, 400);
  setTimeout(run, 1400);
})();
