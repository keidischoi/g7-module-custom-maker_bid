(function () {
  function jobId() {
    var m = (location.pathname || '').match(/\/maker-bids\/(?:jobs\/)?(\d+)/);
    return m ? m[1] : '';
  }
  function banner(text) {
    var el = document.getElementById('cmb-debug-session');
    if (!el) {
      el = document.createElement('div');
      el.id = 'cmb-debug-session';
      el.style.cssText = 'position:relative;z-index:9999;margin:8px 0;padding:8px 10px;border:1px dashed #c9a227;background:#1a1a1a;color:#ffe08a;font:12px/1.4 monospace;white-space:pre-wrap';
      var host = document.querySelector('.cmb-bid-form, .cmb-job-show, main, body');
      if (host && host.firstChild) host.insertBefore(el, host.firstChild);
      else document.body.appendChild(el);
    }
    el.textContent = text;
  }
  var id = jobId();
  if (id) {
    banner('session loading… job=' + id);
    fetch('/api/modules/custom-maker_bids/jobs/' + id + '/viewer', {
      credentials: 'include',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
      .then(function (r) {
        var d = (r.json && (r.json.data || r.json)) || {};
        banner(
          'viewer HTTP ' + r.status +
          '\n' + (d.debug_session || JSON.stringify(d).slice(0, 300)) +
          '\ncan_bid=' + d.can_bid + ' userId=' + (d.userId || d.user_id || '-')
        );
      })
      .catch(function (e) { banner('viewer fetch failed: ' + e); });
  }

  if (document.documentElement.getAttribute('data-cmb-bid-token-v1')) return;
  document.documentElement.setAttribute('data-cmb-bid-token-v1', '1');
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
    var jid = jobId();
    if (!jid) { toast('error', '의뢰를 찾을 수 없습니다.'); return; }
    var amount = parseInt(val(formBox, 'amount').replace(/[^\d]/g, ''), 10);
    var days = parseInt(val(formBox, 'days').replace(/[^\d]/g, ''), 10);
    var message = val(formBox, 'message');
    if (!amount) { toast('error', '견적 금액을 입력해 주세요.'); return; }
    var body = { amount: amount, message: message };
    if (days) body.days = days;
    var path = '/api/modules/custom-maker_bids/jobs/' + jid + '/bids';
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
