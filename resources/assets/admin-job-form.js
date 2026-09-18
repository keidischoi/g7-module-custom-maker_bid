(function () {
  if (window.__cmbAdminJobForm2) return;
  window.__cmbAdminJobForm2 = true;

  var STATUSES = [
    ['pending', '승인대기', 'pending'],
    ['quote_request', '승인', 'approve'],
    ['hold', '보류', 'hold'],
    ['disputed', '분쟁조정', 'dispute'],
    ['done', '완료', 'complete'],
    ['cancelled', '취소', 'cancel']
  ];

  function jobId() {
    var m = (location.pathname || '').match(/maker-bids\/jobs\/(\d+)/);
    if (m) return m[1];
    var t = document.body.innerText.match(/#(\d+)\s/);
    return t ? t[1] : '';
  }

  function currentStatus() {
    var text = document.body.innerText || '';
    if (text.indexOf('상태: 승인대기') >= 0) return 'pending';
    if (text.indexOf('상태: 보류') >= 0) return 'hold';
    if (text.indexOf('상태: 분쟁') >= 0) return 'disputed';
    if (text.indexOf('상태: 완료') >= 0) return 'done';
    if (text.indexOf('상태: 취소') >= 0) return 'cancelled';
    if (text.indexOf('상태: 낙찰') >= 0) return 'awarded';
    if (text.indexOf('상태: 견적') >= 0 || text.indexOf('상태: 입찰') >= 0) return 'quote_request';
    return '';
  }

  function post(id, action) {
    fetch('/api/modules/custom-maker_bids/admin/jobs/' + id + '/' + action, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function () { location.reload(); });
  }

  function hideLegacySizes() {
    ['size_w', 'size_d', 'size_h'].forEach(function (name) {
      document.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
        var wrap = el.parentElement;
        el.style.display = 'none';
        if (wrap) wrap.style.display = 'none';
      });
    });
  }

  function ensureButtons() {
    var id = jobId();
    if (!id) return;
    var bar = null;
    document.querySelectorAll('button').forEach(function (b) {
      var t = (b.textContent || '').replace(/\s+/g, ' ').trim();
      if (t.indexOf('보류') >= 0 || t.indexOf('승인') >= 0) bar = b.parentElement;
    });
    if (!bar || bar.getAttribute('data-cmb-status-bar')) return;
    bar.setAttribute('data-cmb-status-bar', '1');
    var st = currentStatus();
    bar.querySelectorAll('button').forEach(function (b) {
      var t = (b.textContent || '').trim();
      if (t === '승인' || t.indexOf('보류') >= 0 || t.indexOf('취소') >= 0 || t.indexOf('취소') >= 0) b.style.display = 'none';
    });
    STATUSES.forEach(function (row) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'px-3 py-1.5 text-sm rounded-lg border mr-2';
      b.textContent = row[0] === st ? ('✓ ' + row[1] + ' · 현재') : row[1];
      if (row[0] === st) b.style.fontWeight = '700';
      b.addEventListener('click', function () {
        if (!confirm(row[1] + '(으)로 바꿀까요?')) return;
        post(id, row[2]);
      });
      bar.appendChild(b);
    });
  }

  function run() {
    if (!document.querySelector('.cmb-admin')) return;
    hideLegacySizes();
    ensureButtons();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  setTimeout(run, 400);
  setTimeout(run, 1200);
})();
