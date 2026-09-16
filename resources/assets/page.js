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
