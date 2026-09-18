(function () {
  if (document.documentElement.getAttribute('data-cmb-bid-submit-v3')) return;
  document.documentElement.setAttribute('data-cmb-bid-submit-v3', '1');
  function jobId() {
    var m = (location.pathname || '').match(/\/maker-bids\/(?:jobs\/)?(\d+)/);
    return m ? m[1] : '';
  }
  function val(root, name) {
    var el = (root || document).querySelector('[name="' + name + '"]');
    if (el && el.value) return String(el.value).trim();
    el = document.querySelector('.cmb-bid-form [name="' + name + '"]');
    return el && el.value ? String(el.value).trim() : '';
  }
  function toast(type, msg) {
    try { if (window.G7Core && G7Core.toast) { G7Core.toast({ type: type, message: msg }); return; } } catch (e) {}
    alert(msg);
  }
  function bodyFrom(form) {
    var amount = parseInt(val(form, 'amount').replace(/[^\d]/g, ''), 10);
    var days = parseInt(val(form, 'days').replace(/[^\d]/g, ''), 10);
    var message = val(form, 'message');
    if (!amount) { toast('error', '견적 금액을 입력해 주세요.'); return null; }
    var body = { amount: amount, message: message };
    if (days) body.days = days;
    return body;
  }
  function g7Send(path, method, body) {
    var api = window.G7Core && G7Core.api;
    if (api) {
      if (method === 'POST' && api.post) return Promise.resolve(api.post(path, body));
      if (method === 'PATCH' && api.patch) return Promise.resolve(api.patch(path, body));
      if (api.request) return Promise.resolve(api.request({ url: path, method: method, data: body }));
    }
    return fetch(path, {
      method: method,
      credentials: 'include',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body)
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (j) {
        if (!res.ok) { var err = new Error(j.message || 'fail'); err.status = res.status; err.body = j; throw err; }
        return j;
      });
    });
  }
  document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target.closest('button, [role="button"]') : null;
    if (!t) return;
    var isSubmit = t.classList.contains('cmb-bid-submit') || t.getAttribute('data-cmb-bid-submit');
    var isUpdate = t.classList.contains('cmb-bid-update') || t.getAttribute('data-cmb-bid-update');
    if (!isSubmit && !isUpdate) return;
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    var form = t.closest('.cmb-bid-form, #bidform, #editform') || document.querySelector('.cmb-bid-form');
    var id = jobId();
    if (!id) { toast('error', '의뢰를 찾을 수 없습니다.'); return; }
    var body = bodyFrom(form);
    if (!body) return;
    var path, method;
    if (isSubmit) {
      path = '/api/modules/custom-maker_bids/jobs/' + id + '/bids';
      method = 'POST';
    } else {
      var bidId = (form && form.getAttribute('data-bid-id')) || (document.querySelector('[name="bid_id"]') || {}).value || '';
      if (!bidId) { toast('error', '수정할 견적이 없습니다.'); return; }
      path = '/api/modules/custom-maker_bids/jobs/' + id + '/bids/' + bidId;
      method = 'PATCH';
    }
    t.disabled = true;
    g7Send(path, method, body).then(function () {
      t.disabled = false;
      toast('success', isSubmit ? '견적을 등록했습니다.' : '견적을 수정했습니다.');
      setTimeout(function () { location.reload(); }, 400);
    }).catch(function (err) {
      t.disabled = false;
      var msg = (err && (err.message || (err.body && err.body.message))) || '요청에 실패했습니다.';
      if (err && err.status === 401) msg = '로그인이 필요합니다.';
      toast('error', msg);
    });
  }, true);
})();
