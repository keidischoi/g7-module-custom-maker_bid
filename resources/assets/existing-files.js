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

    var action = '/api/modules/custom-maker_bids/jobs/' + encodeURIComponent(id) + '/bids';
    var methodField = 'POST';
    if (isUpdate) {
      var bidId = (formBox && formBox.getAttribute('data-bid-id')) || (document.querySelector('[name="bid_id"]') || {}).value || '';
      if (!bidId) { toast('error', '수정할 견적이 없습니다.'); return; }
      action += '/' + encodeURIComponent(bidId);
      methodField = 'PATCH';
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
    form.acceptCharset = 'UTF-8';
    function add(n, v) {
      var i = document.createElement('input');
      i.type = 'hidden';
      i.name = n;
      i.value = v == null ? '' : String(v);
      form.appendChild(i);
    }
    add('_token', csrf());
    add('_method', methodField === 'PATCH' ? 'PATCH' : 'POST');
    add('amount', amount);
    if (days) add('days', days);
    add('message', message);
    document.body.appendChild(form);

    iframe.onload = function () {
      var text = '';
      try { text = (iframe.contentDocument && iframe.contentDocument.body && iframe.contentDocument.body.innerText) || ''; } catch (err) { text = ''; }
      form.parentNode && form.parentNode.removeChild(form);
      if (/success|"data"|견적/.test(text) && !/unauthor|로그인|인증이 필요|Unauthorized|419/i.test(text)) {
        toast('success', '견적을 저장했습니다.');
        setTimeout(function () { location.reload(); }, 400);
        return;
      }
      if (/419|Page Expired|CSRF/i.test(text)) {
        toast('error', '페이지를 새로고침한 뒤 다시 제출해 주세요.');
        return;
      }
      if (/unauthor|인증이 필요|로그인이 필요/i.test(text)) {
        toast('error', '세션이 API에 안 붙습니다. 관리자에게 G7 API 세션(stateful) 설정을 확인해 주세요.');
        return;
      }
      toast('success', '견적 요청을 보냈습니다.');
      setTimeout(function () { location.reload(); }, 600);
    };
    form.submit();
  }, true);
})();
