/* Lightweight paint only. No MutationObserver (it froze the edit page). */
(function () {
  var API = '/api/modules/custom-maker_bids';
  function jobIdFromPath() {
    var p = location.pathname || '';
    var m = p.match(/\/maker-bids\/(\d+)(?:\/edit)?\/?$/);
    if (m) return m[1];
    m = p.match(/\/admin\/maker-bids\/(?:jobs\/)?(\d+)/);
    return m ? m[1] : '';
  }
  function csrf() {
    var el = document.querySelector('meta[name="csrf-token"]');
    if (el && el.content) return el.content;
    var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    if (!m) return '';
    try { return decodeURIComponent(m[1]); } catch (e) { return m[1]; }
  }
  function getJson(url) {
    return fetch(url, {
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf(),
        'X-XSRF-TOKEN': csrf()
      }
    }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });
  }
  function fileUrl(f) {
    return (f && (f.download_url || f.url || f.thumbnail_url)) || '';
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
      if (kind !== 'archives' && url) {
        var img = document.createElement('img');
        img.src = url;
        img.style.cssText = 'width:64px;height:64px;object-fit:cover;border-radius:8px';
        row.appendChild(img);
      }
      var name = document.createElement('span');
      name.textContent = (f && (f.original_filename || f.name)) || 'file';
      row.appendChild(name);
      box.appendChild(row);
    });
  }
  function boot() {
    var id = jobIdFromPath();
    if (id) {
      getJson(API + '/jobs/' + id).then(function (json) {
        var data = json && (json.data || json);
        if (!data) return;
        paint('[data-cmb-existing-files="images"]', data.images || [], 'images');
        paint('[data-cmb-existing-files="archives"]', data.archives || [], 'archives');
      });
    }
    if (/\/maker-bids\/company\/?$/.test(location.pathname || '')) {
      getJson(API + '/companies/me').then(function (json) {
        var data = json && (json.data || json);
        if (!data) return;
        var logos = data.logo_files || [];
        if (!logos.length && data.logo_url) logos = [{ url: data.logo_url, original_filename: 'logo' }];
        paint('[data-cmb-existing-files="logo"]', logos, 'logo');
      });
    }
  }
  if (document.documentElement.getAttribute('data-cmb-existing-paint')) return;
  document.documentElement.setAttribute('data-cmb-existing-paint', '1');
  setTimeout(boot, 400);
  setTimeout(boot, 1500);
})();
