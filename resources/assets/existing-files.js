(function () {
  if (document.documentElement.getAttribute('data-cmb-bid-formpost')) return;
  document.documentElement.setAttribute('data-cmb-bid-formpost', '1');
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
    if (!id) { toast('error', '의뢰를 찾을 수 없습니다.'); return; }
    var amount = val(formBox, 'amount').replace(/[^\d]/g, '');
    var days = val(formBox, 'days').replace(/[^\d]/g, '');
    var message = val(formBox, 'message');
    if (!amount) { toast('error', '견적 금액을 입력해 주세요.'); return; }
    var action = '/maker-bids/jobs/' + encodeURIComponent(id) + '/bid-save';
    if (isUpdate) {
      var bidId = (formBox && formBox.getAttribute('data-bid-id')) || (document.querySelector('[name="bid_id"]') || {}).value || '';
      if (!bidId) { toast('error', '수정할 견적이 없습니다.'); return; }
      action = '/maker-bids/jobs/' + encodeURIComponent(id) + '/bids/' + encodeURIComponent(bidId) + '/bid-save';
    }
    var iframe = document.getElementById('cmb_bid_iframe');
    if (!iframe) {
      iframe = document.createElement('iframe');
      iframe.id = 'cmb_bid_iframe';
      iframe.name = 'cmb_bid_iframe';
      iframe.style.cssText = 'position:absolute;width:1px;height:1px;left:-9999px;opacity:0';
      document.body.appendChild(iframe);
    }
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = action;
    form.target = 'cmb_bid_iframe';
    function add(n, v) {
      var i = document.createElement('input');
      i.type = 'hidden'; i.name = n; i.value = v == null ? '' : String(v);
      form.appendChild(i);
    }
    add('_token', csrf());
    add('amount', amount);
    if (days) add('days', days);
    add('message', message);
    document.body.appendChild(form);
    iframe.onload = function () {
      var text = '';
      try { text = (iframe.contentDocument && iframe.contentDocument.body && iframe.contentDocument.body.innerText) || ''; } catch (err) {}
      form.parentNode && form.parentNode.removeChild(form);
      if (/404|Not Found/.test(text)) {
        toast('error', '웹 라우트가 없습니다. route:clear 후 다시 시도해 주세요.');
        return;
      }
      if (/로그인이 필요|인증이 필요|Unauthorized|401/.test(text)) {
        toast('error', '로그인 세션을 찾지 못했습니다.');
        return;
      }
      toast('success', '견적을 저장했습니다.');
      setTimeout(function () { location.reload(); }, 400);
    };
    form.submit();
  }, true);
})();
