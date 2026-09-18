(function () {
  if (window.__cmbAdminJobForm) return;
  window.__cmbAdminJobForm = true;

  var STATUSES = [
    ['pending', '승인대기'],
    ['hold', '보류'],
    ['quote_request', '견적요청'],
    ['awarded', '낙찰'],
    ['disputed', '분쟁조정'],
    ['done', '완료'],
    ['cancelled', '취소'],
    ['draft', '임시저장']
  ];

  function hideLegacySizes() {
    ['size_w', 'size_d', 'size_h'].forEach(function (name) {
      document.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
        el.style.display = 'none';
        var wrap = el.parentElement;
        if (wrap) wrap.style.display = 'none';
      });
    });
    document.querySelectorAll('p, label, span, div, h3, h4').forEach(function (el) {
      var t = (el.childNodes.length && el.childNodes[0].nodeType === 3) ? (el.textContent || '') : '';
      if (t.indexOf('최종 크기') >= 0 && t.indexOf('sizes') >= 0) {
        el.style.display = 'none';
      }
    });
  }

  function fillNativeSelect(sel, current) {
    if (!sel || sel.getAttribute('data-cmb-status-full')) return;
    sel.setAttribute('data-cmb-status-full', '1');
    var have = {};
    Array.prototype.forEach.call(sel.options || [], function (o) { have[o.value] = true; });
    STATUSES.forEach(function (pair) {
      if (have[pair[0]]) return;
      var o = document.createElement('option');
      o.value = pair[0];
      o.textContent = pair[1];
      sel.appendChild(o);
    });
    if (current) sel.value = current;
  }

  function enhanceStatus() {
    var sel = document.querySelector('.cmb-admin select[name="status"], select[name="status"]');
    var current = (sel && sel.value) || '';
    if (sel) fillNativeSelect(sel, current);
    document.querySelectorAll('.cmb-admin [name="default_job_status"]').forEach(function (el) {
      if (el.tagName === 'SELECT') fillNativeSelect(el, el.value);
    });
  }

  function run() {
    if (!document.querySelector('.cmb-admin')) return;
    hideLegacySizes();
    enhanceStatus();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', run);
  else run();
  setTimeout(run, 400);
  setTimeout(run, 1200);
})();
