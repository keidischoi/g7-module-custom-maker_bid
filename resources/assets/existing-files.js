(function () {
  if (document.documentElement.getAttribute('data-cmb-bid-token-v1')) return;
  document.documentElement.setAttribute('data-cmb-bid-token-v1', '1');
  function jobId() {
    var m = (location.pathname || '').match(/\/maker-bids\/(?:jobs\/)?(\d+)/);
    return m ? m[1] : '';
  }
  function pick(obj, path) {
    var cur = obj;
    var parts = path.split('.');
    for (var i = 0; i < parts.length; i++) {
      if (!cur) return '';
      cur = cur[parts[i]];
    }
    return cur || '';
  }
  function bidToken() {
    var g = window.G7Core || window.g7 || {};
    var tries = [];
    try { if (g.get) tries.push(g.get('viewer.data.bid_token')); } catch (e) {}
    try { if (g.get) tries.push(g.get('_data.viewer.data.bid_token')); } catch (e) {}
    tries.push(pick(g, 'state.viewer.data.bid_token'));
    tries.push(pick(g, 'data.viewer.data.bid_token'));
    tries.push(pick(window, '__G7_STATE__.viewer.data.bid_token'));
    for (var i = 0; i < tries.length; i++) {
      if (tries[i]) return String(tries[i]);
    }
    try {
      var dump = JSON.stringify(g.state || g.data || g);
      var m = dump.match(/"bid_token":"([^"]+)"/);
      if (m) return m[1];
    } catch (e) {}
    return '';
  }
  function val(root, name) {
    var el = (root || document).querySelector('[name="' + name + '"]');
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
  function csrf() {
    var el = document.querySelector('meta[name="csrf-token"]');
    return el && el.content ? el.content : '';
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
    var formBox = t.closest('.cmb-bid-form, #bidform, #editform') || document.querySelector('.cmb-bid-form');
    var id = jobId();
    var token = bidToken();
    if (!id) { toast('error', '의뢰를 찾을 수 없습니다.'); return; }
    if (!token) { toast('error', '견적 토큰이 없습니다. 페이지를 새로고침 해 주세요.'); return; }
    var amount = parseInt(val(formBox, 'amount').replace(/[^\d]/g, ''), 10);
    var days = parseInt(val(formBox, 'days').replace(/[^\d]/g, ''), 10);
    var message = val(formBox, 'message');
    if (!amount) { toast('error', '견적 금액을 입력해 주세요.'); return; }
    var body = { amount: amount, message: message, bid_token: token };
    if (days) body.days = days;
    var path = '/api/modules/custom-maker_bids/jobs/' + id + '/bids';
    var method = 'POST';
    if (isUpdate) {
      var bidId = (formBox && formBox.getAttribute('data-bid-id')) || (document.querySelector('[name="bid_id"]') || {}).value || '';
      if (!bidId) { toast('error', '수정할 견적이 없습니다.'); return; }
      path += '/' + bidId;
      method = 'PATCH';
    }
    t.disabled = true;
    fetch(path, {
      method: method,
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrf()
      },
      body: JSON.stringify(body)
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (j) { return { ok: res.ok, json: j }; });
    }).then(function (r) {
      t.disabled = false;
      if (!r.ok) {
        toast('error', (r.json && r.json.message) || '제출에 실패했습니다.');
        return;
      }
      toast('success', '견적을 저장했습니다.');
      setTimeout(function () { location.reload(); }, 400);
    }).catch(function () {
      t.disabled = false;
      toast('error', '제출에 실패했습니다.');
    });
  }, true);
})();
