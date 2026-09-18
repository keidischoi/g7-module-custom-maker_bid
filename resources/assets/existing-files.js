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
  if (!id) return;
  banner('debug job=' + id + ' (G7 apiCall submit)');
})();
