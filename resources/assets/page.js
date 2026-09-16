(function () {
  function path() {
    return String(location.pathname || '');
  }

  function jobIdFromPath() {
    var m = path().match(/\/maker-bids\/(?:jobs\/)?(\d+)(?:\/edit)?\/?$/);
    return m ? m[1] : '';
  }

  function isUserEdit() {
    return /\/maker-bids\/\d+\/edit\/?$/.test(path());
  }

  function isUserShow() {
    return /\/maker-bids\/\d+\/?$/.test(path()) && !isUserEdit() && path().indexOf('/admin/') !== 0;
  }

  function isAdminJob() {
    return /\/admin\/maker-bids\/jobs\/\d+\/?$/.test(path());
  }

  function isAdminCompany() {
    return /\/admin\/maker-bids\/companies\/?$/.test(path());
  }

  function setNativeValue(el, value) {
    if (!el) return;
    var tag = (el.tagName || '').toUpperCase();
    var proto = tag === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
    var desc = Object.getOwnPropertyDescriptor(proto, 'value');
    if (desc && desc.set) desc.set.call(el, value == null ? '' : String(value));
    else el.value = value == null ? '' : String(value);
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function fillNamed(name, value, root) {
    var scope = root || document;
    if (value === true) value = '1';
    if (value === false) value = '';
    if (value == null) value = '';
    scope.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
      if (el.type === 'checkbox') {
        el.checked = !!(value && value !== '0' && value !== 'false');
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return;
      }
      if (el.type === 'file') return;
      setNativeValue(el, value);
    });
  }

  function fillJob(d) {
    [
      'title','type','budget_min','budget_max','closes_at','closes_at_local','rush_deadline',
      'status','audience','size_w','size_d','size_h','description',
      'contact_name','contact_phone','contact_email','zipcode','address','address_detail',
      'manager_name','manager_phone','manager_email','revision_count','revision_cost',
      'bidding_status'
    ].forEach(function (k) {
      if (d[k] != null && d[k] !== '') fillNamed(k, d[k]);
    });
    if (d.closes_at_local) fillNamed('closes_at', d.closes_at_local);
    ['rush_fee_enabled','schedule_premium_enabled','revision_enabled','ownership_requested',
     'ext_stl','ext_3mf','ext_obj','ext_step','ext_stp','ext_gcode','ext_fbx','ext_dwg'].forEach(function (k) {
      if (typeof d[k] !== 'undefined') fillNamed(k, d[k]);
    });
    if (d.contact_hours) {
      var parts = String(d.contact_hours).split(/[-~]/);
      if (parts[0]) fillNamed('contact_hours_from', parts[0].trim());
      if (parts[1]) fillNamed('contact_hours_to', parts[1].trim());
    }
  }

  function fillCompany(d) {
    var root = document.querySelector('[dataKey="edit"], .cmb-admin-card');
    ['status','admin_memo','hold_reason','rating_score','rating_count','claim_count',
     'claim_history','report_count','priority','rejected_reason','name','owner_name',
     'phone','email','address'].forEach(function (k) {
      if (typeof d[k] !== 'undefined') fillNamed(k, d[k], root || document);
    });
    ['is_recommended','is_designated'].forEach(function (k) {
      if (typeof d[k] !== 'undefined') fillNamed(k, d[k], root || document);
    });
  }

  function prefillUserEdit() {
    if (!isUserEdit()) return;
    var id = jobIdFromPath();
    if (!id || document.documentElement.getAttribute('data-cmb-edit-filled') === id) return;
    fetch('/api/modules/custom-maker_bids/jobs/' + id, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        document.documentElement.setAttribute('data-cmb-edit-filled', id);
        fillJob((j && j.data) || j || {});
      })
      .catch(function () {});
  }

  function prefillAdminJob() {
    if (!isAdminJob()) return;
    var id = jobIdFromPath();
    if (!id || document.documentElement.getAttribute('data-cmb-admin-job') === id) return;
    fetch('/api/modules/custom-maker_bids/admin/jobs/' + id, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        document.documentElement.setAttribute('data-cmb-admin-job', id);
        fillJob((j && j.data) || j || {});
      })
      .catch(function () {});
  }

  function bindCompanyLoad() {
    if (!isAdminCompany()) return;
    if (document.documentElement.getAttribute('data-cmb-admin-co')) return;
    document.documentElement.setAttribute('data-cmb-admin-co', '1');
    document.addEventListener('click', function (e) {
      var btn = e.target && e.target.closest ? e.target.closest('button') : null;
      if (!btn || (btn.textContent || '').indexOf('불러오기') === -1) return;
      var row = btn.closest('.cmb-admin-row') || btn.closest('[class*="row"]');
      var link = row && row.querySelector('a[href*="/companies"]');
      var href = link ? link.getAttribute('href') : '';
      var m = String(href || '').match(/(\d+)/);
      var title = row && row.querySelector('.cmb-admin-row-title');
      if (!m && title) m = String(title.textContent || '').match(/#(\d+)/);
      if (!m) return;
      fetch('/api/modules/custom-maker_bids/admin/companies/' + m[1], { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          var d = (j && j.data) || j || {};
          fillCompany(d);
        })
        .catch(function () {});
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
      if (form) {
        form.classList.remove('hidden');
        form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
    if (edit && edit.parentNode) edit.parentNode.insertBefore(btn, edit.nextSibling);
  }

  function loadViewer() {
    if (!isUserShow()) return;
    var id = jobIdFromPath();
    if (!id) return;
    fetch('/api/modules/custom-maker_bids/jobs/' + id + '/viewer', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) { ensureBidCta((j && (j.data || j)) || {}); })
      .catch(function () {});
  }

  function run() {
    prefillUserEdit();
    prefillAdminJob();
    bindCompanyLoad();
    loadViewer();
  }

  run();
  document.addEventListener('DOMContentLoaded', run);
  setTimeout(run, 300);
  setTimeout(run, 1000);
  setTimeout(run, 2000);
})();
