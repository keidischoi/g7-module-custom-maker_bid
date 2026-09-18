(function () {
  if (document.documentElement.getAttribute('data-cmb-bid-send-v4')) return;
  document.documentElement.setAttribute('data-cmb-bid-send-v4', '1');
  function jobId() {
    var m = (location.pathname || '').match(/\/maker-bids\/(?:jobs\/)?(\d+)/);
    return m ? m[1] : '';
  }
  function val(root, name) {
    var scope = root || document;
    var el = scope.querySelector('[name="' + name + '"]');
    if (el && el.value) return String(el.value).trim();
    var all = document.querySelectorAll('[name="' + name + '"]');
    for (var i = 0; i < all.length; i++) {
      if (all[i].value) return String(all[i].value).trim();
    }
    return '';
  }
  function toast(type, msg) {
    try { if (window.G7Core && G7Core.toast) { G7Core.toast({ type: type, message: msg }); return; } } catch (e) {}
    alert(msg);
  }
  function actorHeaders() {
    var h = { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta && meta.content) {
      h['X-CSRF-TOKEN'] = meta.content;
      h['X-XSRF-TOKEN'] = meta.content;
    }
    var m = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    if (m) {
      try { h['X-XSRF-TOKEN'] = decodeURIComponent(m[1]); } catch (e) {}
    }
    return h;
  }
  function send(path, method, body) {
    return fetch('/sanctum/csrf-cookie', { credentials: 'include' }).catch(function () {}).then(function () {
      return fetch(path, {
        method: method,
        credentials: 'include',
        headers: actorHeaders(),
        body: JSON.stringify(body)
      });
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (j) {
        if (!res.ok) {
          var err = new Error(j.message || 'fail');
          err.status = res.status;
          err.body = j;
          throw err;
        }
        return j;
      });
    });
  }
  document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target.closest('button, [role="button"]') : null;
    if (!t) return;
    var isSubmit = t.classList.contains('cmb-bid-send') || t.classList.contains('cmb-bid-submit');
    var isUpdate = t.classList.contains('cmb-bid-send-update') || t.classList.contains('cmb-bid-update');
    if (!isSubmit && !isUpdate) return;
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    var form = t.closest('.cmb-bid-form, #bidform, #editform') || document.querySelector('.cmb-bid-form');
    var id = jobId();
    if (!id) { toast('error', '의뢰를 찾을 수 없습니다.'); return; }
    var amount = parseInt(val(form, 'amount').replace(/[^\d]/g, ''), 10);
    var days = parseInt(val(form, 'days').replace(/[^\d]/g, ''), 10);
    var message = val(form, 'message');
    if (!amount) { toast('error', '견적 금액을 입력해 주세요.'); return; }
    var body = { amount: amount, message: message };
    if (days) body.days = days;
    var path = '/api/modules/custom-maker_bids/jobs/' + id + '/bids';
    var method = 'POST';
    if (isUpdate) {
      var bidId = (form && form.getAttribute('data-bid-id')) || (document.querySelector('[name="bid_id"]') || {}).value || '';
      if (!bidId) { toast('error', '수정할 견적이 없습니다.'); return; }
      path += '/' + bidId;
      method = 'PATCH';
    }
    t.disabled = true;
    send(path, method, body).then(function () {
      t.disabled = false;
      toast('success', '견적을 저장했습니다.');
      setTimeout(function () { location.reload(); }, 400);
    }).catch(function (err) {
      t.disabled = false;
      toast('error', (err && err.message) || '요청에 실패했습니다.');
    });
  }, true);
})();
