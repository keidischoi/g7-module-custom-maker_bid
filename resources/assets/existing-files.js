/* Paint already-uploaded files on job/company edit. */
(function () {
  var API = '/api/modules/custom-maker_bids';
  function jobIdFromPath() {
    var m = (location.pathname || '').match(/\/maker-bids\/(\d+)(?:\/edit)?\/?$/);
    return m ? m[1] : '';
  }
  function csrf() {
    var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    if (!m) return '';
    try { return decodeURIComponent(m[1]); } catch (e) { return m[1]; }
  }
  function getJson(url) {
    return fetch(url, {
      credentials: 'include',
      headers: { Accept: 'application/json', 'X-XSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });
  }
  function fileUrl(f) {
    return f.download_url || f.url || f.thumbnail_url || (f.hash ? API + '/files/' + f.hash : '');
  }
  function fileName(f) {
    return f.original_filename || f.file_name || f.name || 'file';
  }
  function paint(box, files, kind) {
    if (!box) return;
    Array.prototype.slice.call(box.querySelectorAll('.cmb-existing-item, .cmb-existing-js')).forEach(function (n) {
      n.parentNode.removeChild(n);
    });
    if (!files || !files.length) {
      box.style.display = 'none';
      return;
    }
    box.style.display = '';
    files.forEach(function (f) {
      var row = document.createElement('div');
      row.className = 'cmb-existing-item cmb-existing-js';
      var url = fileUrl(f);
      if (kind === 'images' || kind === 'logo' || f.is_image) {
        var a = document.createElement('a');
        a.href = url || '#';
        a.target = '_blank';
        var img = document.createElement('img');
        img.src = url;
        img.alt = fileName(f);
        img.className = 'cmb-existing-thumb';
        a.appendChild(img);
        row.appendChild(a);
      } else {
        var ic = document.createElement('span');
        ic.className = 'cmb-existing-icon';
        ic.textContent = '📎';
        row.appendChild(ic);
      }
      var name = document.createElement('a');
      name.href = url || '#';
      name.target = '_blank';
      name.className = 'cmb-existing-name text-sm underline truncate';
      name.textContent = fileName(f);
      row.appendChild(name);
      box.appendChild(row);
    });
  }
  function boot() {
    var id = jobIdFromPath();
    if (id) {
      getJson(API + '/jobs/' + id + '/edit').then(function (json) {
        var data = json && (json.data || json);
        if (!data) return;
        paint(document.querySelector('[data-cmb-existing-files="images"]'), data.images || [], 'images');
        paint(document.querySelector('[data-cmb-existing-files="archives"]'), data.archives || [], 'archives');
      });
    }
    if (/\/maker-bids\/company\/?$/.test(location.pathname || '')) {
      getJson(API + '/companies/me').then(function (json) {
        var data = json && (json.data || json);
        if (!data) return;
        var logos = data.logo_files || [];
        if (!logos.length && data.logo_url) {
          logos = [{ url: data.logo_url, download_url: data.logo_url, original_filename: 'logo', is_image: true }];
        }
        paint(document.querySelector('[data-cmb-existing-files="logo"]'), logos, 'logo');
      });
    }
  }
  if (!document.documentElement.getAttribute('data-cmb-existing-paint')) {
    document.documentElement.setAttribute('data-cmb-existing-paint', '1');
    setTimeout(boot, 200);
    setTimeout(boot, 800);
    setTimeout(boot, 1800);
  }
})();
