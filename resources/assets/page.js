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

  function unwrap(j) {
    var d = j;
    var i;
    for (i = 0; i < 3; i++) {
      if (d && d.data !== undefined && !Array.isArray(d.data)) d = d.data;
      else break;
    }
    return d && typeof d === 'object' ? d : {};
  }

  function jobIdFromPath() {
    var m = path().match(/\/maker-bids\/(?:jobs\/)?(\d+)(?:\/edit)?$/);
    return m ? m[1] : '';
  }

  function isUserEdit() {
    return /\/maker-bids\/\d+\/edit$/.test(path());
  }

  function isUserShow() {
    return /\/maker-bids\/\d+$/.test(path()) && path().indexOf('/admin/') < 0;
  }

  function isUserCompany() {
    return /\/maker-bids\/company$/.test(path());
  }

  function isAdminJob() {
    return /\/admin\/maker-bids\/jobs\/\d+$/.test(path());
  }

  function isAdminCompany() {
    return /\/admin\/maker-bids\/companies$/.test(path());
  }

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
      if (desc && desc.set) desc.set.call(el, str);
      else el.value = str;
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
      var v = !!d[k];
      map[prefix + '.' + k] = v;
      fillNamed(k, v);
    });
    setLocal(map);
  }

  function applyJob(d) {
    applyPrefixed('form', d, [
      'title','type','status','audience','budget_min','budget_max','description',
      'closes_at','rush_deadline','size_w','size_d','size_h',
      'contact_name','contact_phone','contact_email','zipcode','address','address_detail',
      'manager_name','manager_phone','manager_email','revision_count','revision_cost',
      'bidding_status'
    ], [
      'rush_fee_enabled','schedule_premium_enabled','revision_enabled','ownership_requested',
      'ext_stl','ext_3mf','ext_obj','ext_step','ext_stp','ext_gcode','ext_fbx','ext_dwg'
    ]);
    if (d.contact_hours) {
      var parts = String(d.contact_hours).split(/[-~]/);
      var map = {};
      if (parts[0]) {
        map['form.contact_hours_from'] = parts[0].trim();
        fillNamed('contact_hours_from', parts[0].trim());
      }
      if (parts[1]) {
        map['form.contact_hours_to'] = parts[1].trim();
        fillNamed('contact_hours_to', parts[1].trim());
      }
      setLocal(map);
    }
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

  function fetchJson(url, cb) {
    fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        var d = unwrap(j);
        if (!d || (typeof d === 'object' && !d.id && !d.title && !d.name && !d.status)) return;
        cb(d);
        setTimeout(function () { cb(d); }, 250);
        setTimeout(function () { cb(d); }, 900);
      })
      .catch(function () {});
  }

  function prefillUserEdit() {
    if (!isUserEdit()) return;
    var id = jobIdFromPath();
    if (id) fetchJson('/api/modules/custom-maker_bids/jobs/' + id, applyJob);
  }

  function prefillAdminJob() {
    if (!isAdminJob()) return;
    var id = jobIdFromPath();
    if (id) fetchJson('/api/modules/custom-maker_bids/admin/jobs/' + id, applyJob);
  }

  function prefillUserCompany() {
    if (!isUserCompany()) return;
    fetchJson('/api/modules/custom-maker_bids/companies/me', applyUserCompany);
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
      fetchJson('/api/modules/custom-maker_bids/admin/companies/' + m[1], applyAdminCompany);
    }, true);
  }

  function ensureBidCta(viewer) {
    if (!isUserShow()) return;
    if (document.getElementById('cmb-bid-cta')) return;
    if (!viewer || !viewer.can_bid || viewer.my_bid) return;
    var edit = document.getElementById('edit_link') || document.querySelector('a[href*="/edit"]');
    var btn = document.createElement('button');
    btn.id = 'cmb-bid-cta';
    btn.type = 'button';
    btn.textContent = '입찰하기';
    btn.className = 'inline-block ml-2 px-3 py-1.5 text-sm rounded-lg bg-gray-900 dark:bg-white text-white dark:text-gray-900';
    btn.addEventListener('click', function () {
      var form = document.getElementById('bidform');
      if (form) form.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
    if (edit && edit.parentNode) edit.parentNode.insertBefore(btn, edit.nextSibling);
  }

  function loadViewer() {
    if (!isUserShow()) return;
    var id = jobIdFromPath();
    if (!id) return;
    fetchJson('/api/modules/custom-maker_bids/jobs/' + id + '/viewer', ensureBidCta);
  }

  function run() {
    prefillUserEdit();
    prefillAdminJob();
    prefillUserCompany();
    bindCompanyLoad();
    loadViewer();
  }

  run();
  document.addEventListener('DOMContentLoaded', run);
  setTimeout(run, 400);
  setTimeout(run, 1200);
})();
