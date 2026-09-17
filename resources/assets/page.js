(function () {
  function path() {
    return String(location.pathname || '').replace(/\/+$/, '') || '/';
  }
  function dispatch(handler, params) {
    if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
      window.G7Core.dispatch({ handler: handler, params: params || {} });
    }
  }
  function setLocal(map) {
    var params = { target: 'local' };
    var key;
    for (key in map) {
      if (Object.prototype.hasOwnProperty.call(map, key)) params[key] = map[key];
    }
    dispatch('setState', params);
  }
  function g7Get(p) {
    try {
      if (window.G7Core && window.G7Core.state && typeof window.G7Core.state.get === 'function') {
        return window.G7Core.state.get(p);
      }
    } catch (e) {}
    return null;
  }
  function unwrap(j) {
    var d = j;
    var i;
    for (i = 0; i < 4; i++) {
      if (d && d.data !== undefined && !Array.isArray(d.data) && typeof d.data === 'object') d = d.data;
      else break;
    }
    return d && typeof d === 'object' ? d : {};
  }
  function usable(d) {
    return !!(d && (d.id || d.title || d.name || d.type || d.phone || d.email));
  }
  function jobIdFromPath() {
    var m = path().match(/\/maker-bids\/(?:jobs\/)?(\d+)(?:\/edit)?$/);
    return m ? m[1] : '';
  }
  function isUserEdit() { return /\/maker-bids\/\d+\/edit$/.test(path()); }
  function isUserShow() { return /\/maker-bids\/\d+$/.test(path()) && path().indexOf('/admin/') < 0; }
  function isUserCompany() { return /\/maker-bids\/company$/.test(path()); }
  function isAdminJob() { return /\/admin\/maker-bids\/jobs\/\d+$/.test(path()); }
  function isAdminCompany() { return /\/admin\/maker-bids\/companies$/.test(path()); }

  function fillNamed(name, value) {
    document.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
      if (!el || el.type === 'file') return;
      if (el.type === 'checkbox') {
        el.checked = !!(value && value !== '0' && value !== 'false');
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return;
      }
      var tag = (el.tagName || '').toUpperCase();
      var proto = tag === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
      var desc = Object.getOwnPropertyDescriptor(proto, 'value');
      var str = value == null ? '' : String(value);
      if (desc && desc.set) desc.set.call(el, str); else el.value = str;
      el.dispatchEvent(new Event('input', { bubbles: true }));
      el.dispatchEvent(new Event('change', { bubbles: true }));
    });
  }

  function applyPrefixed(prefix, d, keys, bools) {
    var map = {};
    (keys || []).forEach(function (k) {
      var v = d[k];
      if (k === 'closes_at' && d.closes_at_local) v = d.closes_at_local;
      if (v == null) v = '';
      map[prefix + '.' + k] = v;
      fillNamed(k, v);
    });
    (bools || []).forEach(function (k) {
      map[prefix + '.' + k] = !!d[k];
      fillNamed(k, !!d[k]);
    });
    if (d.sizes) map[prefix + '.sizes'] = typeof d.sizes === 'string' ? d.sizes : JSON.stringify(d.sizes);
    if (d.sizes_json) map[prefix + '.sizes'] = d.sizes_json;
    setLocal(map);
  }

  function applyJob(d) {
    applyPrefixed('form', d, [
      'title','type','status','audience','budget_min','budget_max','description',
      'closes_at','rush_deadline','size_w','size_d','size_h',
      'contact_name','contact_phone','contact_email','zipcode','address','address_detail',
      'manager_name','manager_phone','manager_email','revision_count','revision_cost'
    ], [
      'rush_fee_enabled','schedule_premium_enabled','revision_enabled','ownership_requested',
      'ext_stl','ext_3mf','ext_obj','ext_step','ext_stp','ext_gcode','ext_fbx','ext_dwg'
    ]);
  }
  function applyUserCompany(d) {
    applyPrefixed('company', d, [
      'kind','name','business_no','bio','homepage_url','portfolio_url',
      'manager_name','phone','email','zipcode','address','address_detail'
    ], []);
  }
  function applyAdminCompany(d) {
    applyPrefixed('edit', d, [
      'id','status','admin_memo','hold_reason','rating_score','rating_count',
      'claim_count','claim_history','report_count','priority','rejected_reason'
    ], ['is_recommended','is_designated']);
  }

  function fromState(ids) {
    var i, d;
    for (i = 0; i < ids.length; i++) {
      d = unwrap(g7Get(ids[i]));
      if (usable(d)) return d;
    }
    return null;
  }

  var JOB_KEYS = ['job.data','job','_data.job.data','_data.job','dataSources.job.data','dataSources.job'];
  var ME_KEYS = ['me.data','me','_data.me.data','_data.me'];

  function prefillUserEdit() {
    if (!isUserEdit()) return;
    var d = fromState(JOB_KEYS);
    if (usable(d)) applyJob(d);
  }
  function prefillAdminJob() {
    if (!isAdminJob()) return;
    var d = fromState(JOB_KEYS);
    if (usable(d)) applyJob(d);
  }
  function prefillUserCompany() {
    if (!isUserCompany()) return;
    var d = fromState(ME_KEYS);
    if (usable(d)) applyUserCompany(d);
  }
  function bindCompanyLoad() {
    if (!isAdminCompany()) return;
    if (document.documentElement.getAttribute('data-cmb-admin-co')) return;
    document.documentElement.setAttribute('data-cmb-admin-co', '1');
    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('button') : null;
      if (!btn || (btn.textContent || '').indexOf('불러오기') === -1) return;
      var row = btn.closest('.cmb-admin-row');
      var title = row && row.querySelector('.cmb-admin-row-title');
      var m = title ? String(title.textContent || '').match(/#(\d+)/) : null;
      if (!m) return;
      var list = fromState(['companies.data','companies','_data.companies.data']);
      var rowd = null;
      if (Array.isArray(list)) {
        list.forEach(function (c) { if (c && String(c.id) === m[1]) rowd = c; });
      }
      if (rowd) applyAdminCompany(rowd);
    }, true);
  }

  function run() {
    prefillUserEdit();
    prefillAdminJob();
    prefillUserCompany();
    bindCompanyLoad();
  }
  run();
  document.addEventListener('DOMContentLoaded', run);
  setTimeout(run, 200);
  setTimeout(run, 600);
  setTimeout(run, 1500);
  setTimeout(run, 3000);
})();

/* cmb-pager */
(function () {
  function qs(name) {
    try { return new URLSearchParams(location.search).get(name); } catch (e) { return null; }
  }
  function setParam(param, value) {
    var u;
    try { u = new URL(location.href); } catch (e) { return; }
    if (value == null || value === '' || Number(value) <= 1 && param.indexOf('page') >= 0 && String(value) === '1') {
      // keep page=1 explicit for clarity on first page navigation from page 2+
    }
    u.searchParams.set(param, String(value));
    if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
      window.G7Core.dispatch({ handler: 'navigate', params: { path: u.pathname + u.search } });
      return;
    }
    location.href = u.pathname + u.search;
  }
  function pagesAround(cur, last) {
    var out = [];
    var start = Math.max(1, cur - 2);
    var end = Math.min(last, cur + 2);
    if (start > 1) out.push(1);
    if (start > 2) out.push('…');
    var i;
    for (i = start; i <= end; i++) out.push(i);
    if (end < last - 1) out.push('…');
    if (end < last) out.push(last);
    return out;
  }
  function render(el) {
    var page = parseInt(el.getAttribute('data-page') || '1', 10) || 1;
    var total = parseInt(el.getAttribute('data-total') || '0', 10) || 0;
    var per = parseInt(el.getAttribute('data-per-page') || '10', 10) || 10;
    var last = parseInt(el.getAttribute('data-last-page') || '0', 10) || Math.max(1, Math.ceil(total / per));
    var param = el.getAttribute('data-param') || 'page';
    if (total <= per && last <= 1) {
      el.classList.add('is-empty');
      el.hidden = true;
      el.innerHTML = '';
      return;
    }
    el.hidden = false;
    el.classList.remove('is-empty');
    el.innerHTML = '';
    function btn(label, target, opts) {
      opts = opts || {};
      var a = document.createElement(opts.disabled ? 'span' : 'button');
      a.className = 'cmb-pager-btn' + (opts.active ? ' is-active' : '') + (opts.disabled ? ' is-disabled' : '');
      a.textContent = label;
      if (!opts.disabled && !opts.ellipsis) {
        a.type = 'button';
        a.addEventListener('click', function (e) {
          e.preventDefault();
          setParam(param, target);
        });
      }
      el.appendChild(a);
    }
    btn('이전', page - 1, { disabled: page <= 1 });
    pagesAround(page, last).forEach(function (n) {
      if (n === '…') {
        var s = document.createElement('span');
        s.className = 'cmb-pager-meta';
        s.textContent = '…';
        el.appendChild(s);
        return;
      }
      btn(String(n), n, { active: n === page });
    });
    btn('다음', page + 1, { disabled: page >= last });
    var meta = document.createElement('span');
    meta.className = 'cmb-pager-meta';
    meta.textContent = page + ' / ' + last + ' · ' + total + '건';
    el.appendChild(meta);
  }
  function scan() {
    document.querySelectorAll('[data-cmb-pager]').forEach(render);
  }
  scan();
  document.addEventListener('DOMContentLoaded', scan);
  setTimeout(scan, 200);
  setTimeout(scan, 800);
  setTimeout(scan, 1600);
  if (!window.__cmbPagerObs) {
    window.__cmbPagerObs = new MutationObserver(function () { scan(); });
    try { window.__cmbPagerObs.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-page', 'data-total', 'data-last-page'] }); } catch (e) {}
  }
})();

/* cmb-list-cards: ensure form.css + visible card classes on list rows */
(function () {
  var FORM_CSS = '/api/modules/custom-maker_bids/assets/form.css?v=0.9.14';
  var ITEM_RE = /(^|\s)(cmb-job-card|cmb-bid-card|cmb-company-card|cmb-list-item|cmb-section-card|cmb-empty|cmb-pager)(\s|$)/;

  function ensureFormCss() {
    var existing = document.querySelector('link[href*="custom-maker_bids/assets/form.css"]');
    if (existing) {
      if (existing.href && existing.href.indexOf('v=0.9.14') < 0) {
        existing.href = FORM_CSS;
      }
      return;
    }
    if (document.getElementById('cmb-form-css-page')) return;
    var link = document.createElement('link');
    link.id = 'cmb-form-css-page';
    link.rel = 'stylesheet';
    link.href = FORM_CSS;
    document.head.appendChild(link);
  }

  function ensureItemClass(el) {
    if (!el || el.nodeType !== 1) return;
    var cls = el.getAttribute('class') || '';
    if (ITEM_RE.test(cls)) {
      if (cls.indexOf('cmb-list-item') < 0 && /(cmb-job-card|cmb-bid-card|cmb-company-card)/.test(cls)) {
        el.classList.add('cmb-list-item');
      }
      return;
    }
    // Bare row inside a card list → force a job-card box
    el.classList.add('cmb-job-card', 'cmb-list-item');
  }

  function scan() {
    ensureFormCss();
    document.querySelectorAll('.cmb-card-list').forEach(function (list) {
      Array.prototype.forEach.call(list.children, ensureItemClass);
    });
    document.querySelectorAll('.cmb-section-card').forEach(function (sec) {
      var cls = sec.getAttribute('class') || '';
      if (cls.indexOf('cmb-section-card') >= 0) {
        /* already boxed via CSS */
      }
    });
  }

  scan();
  document.addEventListener('DOMContentLoaded', scan);
  setTimeout(scan, 200);
  setTimeout(scan, 800);
  setTimeout(scan, 1600);
  if (!window.__cmbListCardObs) {
    window.__cmbListCardObs = new MutationObserver(function () { scan(); });
    try {
      window.__cmbListCardObs.observe(document.documentElement, { childList: true, subtree: true });
    } catch (e) {}
  }
})();
