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
  function g7Viewer() {
    var g = window.G7Core || window.g7 || {};
    var paths = ['viewer.data', '_data.viewer.data', 'state.viewer.data', 'data.viewer.data'];
    for (var i = 0; i < paths.length; i++) {
      try {
        if (g.get) {
          var v = g.get(paths[i]);
          if (v) return v;
        }
      } catch (e) {}
    }
    try {
      var dump = JSON.stringify(g.state || g.data || {});
      var m = dump.match(/"can_bid":(true|false)/);
      return { raw: dump.slice(0, 200), can_bid_match: m && m[1] };
    } catch (e) {
      return null;
    }
  }
  var id = jobId();
  if (id) {
    var g7 = g7Viewer();
    banner('job=' + id + '\nG7 state: ' + JSON.stringify(g7).slice(0, 400) + '\nraw fetch…');
    fetch('/api/modules/custom-maker_bids/jobs/' + id + '/viewer', {
      credentials: 'include',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function (r) { return r.json().then(function (j) { return { status: r.status, json: j }; }); })
      .then(function (r) {
        var d = (r.json && (r.json.data || r.json)) || {};
        banner(
          'job=' + id +
          '\nG7 state: ' + JSON.stringify(g7).slice(0, 400) +
          '\nraw HTTP ' + r.status + ' ' + (d.debug_session || '') +
          ' can_bid=' + d.can_bid
        );
      })
      .catch(function (e) { banner('fetch failed ' + e); });
  }
})();
