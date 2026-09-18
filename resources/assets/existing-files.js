/* Lightweight paint + bid submit */
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
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrf(), 'X-XSRF-TOKEN': csrf() }
    }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });
  }
  function fileUrl(f) { return (f && (f.download_url || f.url || f.thumbnail_url)) || ''; }
  function paint(sel, files, kind) {
    var box = document.querySelector(sel);
    if (!box) return;
    files = files || [];
    Array.prototype.slice.call(box.querySelectorAll('.cmb-existing-js')).forEach(function (n) { n.parentNode.removeChild(n); });
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
  if (!document.documentElement.getAttribute('data-cmb-existing-paint')) {
    document.documentElement.setAttribute('data-cmb-existing-paint', '1');
    setTimeout(boot, 400);
    setTimeout(boot, 1500);
  }
})();
(function () {
  if (document.documentElement.getAttribute('data-cmb-bid-submit-v2')) return;
  document.documentElement.setAttribute('data-cmb-bid-submit-v2', '1');
  var API = '/api/modules/custom-maker_bids';
  function jobId() {
    var m = (location.pathname || '').match(/\/maker-bids\/(?:jobs\/)?(\d+)/);
    return m ? m[1] : '';
  }
  function csrf() {
    var el = document.querySelector('meta[name="csrf-token"]');
    if (el && el.content) return el.content;
    var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    if (!m) return '';
    try { return decodeURIComponent(m[1]); } catch (e) { return m[1]; }
  }
  function val(root, name) {
    var el = (root || document).querySelector('[name="' + name + '"]');
    if (el && el.value) return String(el.value).trim();
    el = document.querySelector('.cmb-bid-form [name="' + name + '"], #bidform [name="' + name + '"], #editform [name="' + name + '"]');
    return el && el.value ? String(el.value).trim() : '';
  }
  function headers() {
    var h = { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    var t = csrf();
    if (t) { h['X-CSRF-TOKEN'] = t; h['X-XSRF-TOKEN'] = t; }
    return h;
  }
  function toast(type, msg) {
    try { if (window.G7Core && G7Core.toast) G7Core.toast({ type: type, message: msg }); else alert(msg); } catch (e) { alert(msg); }
  }
  function bodyFrom(form) {
    var amount = parseInt(val(form, 'amount').replace(/[^\d]/g, ''), 10);
    var days = parseInt(val(form, 'days').replace(/[^\d]/g, ''), 10);
    var message = val(form, 'message');
    if (!amount || amount < 1) { toast('error', '견적 금액을 입력해 주세요.'); return null; }
    var body = { amount: amount, message: message };
    if (days) body.days = days;
    return body;
  }
  function send(url, method, body, btn) {
    if (btn) btn.disabled = true;
    fetch(url, { method: method, credentials: 'include', headers: headers(), body: JSON.stringify(body) })
      .then(function (res) { return res.json().catch(function () { return {}; }).then(function (j) { return { ok: res.ok, status: res.status, json: j }; }); })
      .then(function (r) {
        if (btn) btn.disabled = false;
        if (!r.ok) {
          var msg = (r.json && r.json.message) || '요청에 실패했습니다.';
          if (r.status === 401) msg = '로그인이 필요합니다.';
          toast('error', msg);
          return;
        }
        toast('success', method === 'POST' ? '견적을 등록했습니다.' : '견적을 수정했습니다.');
        setTimeout(function () { location.reload(); }, 400);
      })
      .catch(function () { if (btn) btn.disabled = false; toast('error', '요청에 실패했습니다.'); });
  }
  document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target.closest('button, [role="button"], a') : null;
    if (!t) return;
    var isSubmit = t.classList.contains('cmb-bid-submit') || t.getAttribute('data-cmb-bid-submit');
    var isUpdate = t.classList.contains('cmb-bid-update') || t.getAttribute('data-cmb-bid-update');
    if (!isSubmit && !isUpdate) return;
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    var form = t.closest('.cmb-bid-form, [dataKey="bid"], #bidform, #editform') || document.querySelector('.cmb-bid-form');
    var id = jobId();
    if (!id) { toast('error', '의뢰를 찾을 수 없습니다.'); return; }
    var body = bodyFrom(form);
    if (!body) return;
    if (isSubmit) { send(API + '/jobs/' + id + '/bids', 'POST', body, t); return; }
    var bidId = (form && form.getAttribute('data-bid-id')) || '';
    if (!bidId) {
      var hid = document.querySelector('[name="bid_id"]');
      bidId = hid ? hid.value : '';
    }
    if (!bidId) { toast('error', '수정할 견적을 찾을 수 없습니다.'); return; }
    send(API + '/jobs/' + id + '/bids/' + bidId, 'PATCH', body, t);
  }, true);
})();
