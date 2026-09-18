/* Keep painting after G7 remounts the edit form. */
(function () {
  var API = '/api/modules/custom-maker_bids';
  var lastImages = [];
  var lastArchives = [];
  var lastLogos = [];

  function jobIdFromPath() {
    var p = location.pathname || '';
    var m = p.match(/\/maker-bids\/(\d+)(?:\/edit)?\/?$/);
    if (m) return m[1];
    m = p.match(/\/admin\/maker-bids\/(?:jobs\/)?(\d+)/);
    return m ? m[1] : '';
  }
  function csrf() {
    var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    if (!m) return '';
    try { return decodeURIComponent(m[1]); } catch (e) { return m[1]; }
  }
  function getJson(url) {
    return fetch(url, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });
  }
  function fileUrl(f) {
    if (!f) return '';
    return f.download_url || f.url || f.thumbnail_url || (f.hash ? API + '/files/' + f.hash : '');
  }
  function fileName(f) {
    return (f && (f.original_filename || f.file_name || f.name)) || 'file';
  }
  function paint(sel, files, kind) {
    var box = document.querySelector(sel);
    if (!box) return;
    files = files || [];
    Array.prototype.slice.call(box.querySelectorAll('.cmb-existing-js')).forEach(function (n) {
      n.parentNode.removeChild(n);
    });
    if (!files.length) return;
    box.style.display = '';
    files.forEach(function (f) {
      var row = document.createElement('div');
      row.className = 'cmb-existing-item cmb-existing-js';
      row.style.cssText = 'display:flex;align-items:center;gap:8px;margin:6px 0';
      var url = fileUrl(f);
      if (kind !== 'archives') {
        var a = document.createElement('a');
        a.href = url || '#';
        a.target = '_blank';
        var img = document.createElement('img');
        img.src = url;
        img.alt = fileName(f);
        img.style.cssText = 'width:64px;height:64px;object-fit:cover;border-radius:8px;background:#111';
        a.appendChild(img);
        row.appendChild(a);
      } else {
        var ic = document.createElement('span');
        ic.textContent = '📎';
        row.appendChild(ic);
      }
      var name = document.createElement('a');
      name.href = url || '#';
      name.target = '_blank';
      name.textContent = fileName(f);
      name.style.cssText = 'font-size:13px;text-decoration:underline';
      row.appendChild(name);
      box.appendChild(row);
    });
  }
  function applyCached() {
    paint('[data-cmb-existing-files="images"]', lastImages, 'images');
    paint('[data-cmb-existing-files="archives"]', lastArchives, 'archives');
    paint('[data-cmb-existing-files="logo"]', lastLogos, 'logo');
  }
  function load() {
    var id = jobIdFromPath();
    if (id) {
      getJson(API + '/jobs/' + id + '/edit').then(function (json) {
        var data = json && (json.data || json);
        if (!data) return;
        lastImages = data.images || [];
        lastArchives = data.archives || [];
        applyCached();
      });
    }
    if (/\/maker-bids\/company\/?$/.test(location.pathname || '') || /\/admin\/maker-bids\/companies\//.test(location.pathname || '')) {
      var companyUrl = /\/admin\/maker-bids\/companies\/(\d+)/.exec(location.pathname || '');
      var url = companyUrl ? API + '/admin/companies/' + companyUrl[1] : API + '/companies/me';
      getJson(url).then(function (json) {
        var data = json && (json.data || json);
        if (!data) return;
        lastLogos = data.logo_files || [];
        if (!lastLogos.length && data.logo_url) {
          lastLogos = [{ url: data.logo_url, download_url: data.logo_url, original_filename: 'logo', is_image: true }];
        }
        applyCached();
      });
    }
  }
  function boot() {
    load();
    applyCached();
  }
  if (document.documentElement.getAttribute('data-cmb-existing-paint')) return;
  document.documentElement.setAttribute('data-cmb-existing-paint', '1');
  [200, 600, 1200, 2500, 5000].forEach(function (ms) { setTimeout(boot, ms); });
  var obs = new MutationObserver(function () { applyCached(); });
  if (document.body) {
    obs.observe(document.body, { childList: true, subtree: true });
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      obs.observe(document.body, { childList: true, subtree: true });
    });
  }
})();
