(function () {
  function jobIdFromPath() {
    var m = String(location.pathname || '').match(/\/maker-bids\/(\d+)(?:\/edit)?\/?$/);
    return m ? m[1] : '';
  }

  function isEdit() {
    return /\/maker-bids\/\d+\/edit\/?$/.test(location.pathname || '');
  }

  function isShow() {
    return /\/maker-bids\/\d+\/?$/.test(location.pathname || '') && !isEdit();
  }

  function setNativeValue(el, value) {
    if (!el) return;
    var proto = el.tagName === 'TEXTAREA' ? window.HTMLTextAreaElement.prototype : window.HTMLInputElement.prototype;
    var desc = Object.getOwnPropertyDescriptor(proto, 'value');
    if (desc && desc.set) desc.set.call(el, value == null ? '' : String(value));
    else el.value = value == null ? '' : String(value);
    el.dispatchEvent(new Event('input', { bubbles: true }));
    el.dispatchEvent(new Event('change', { bubbles: true }));
  }

  function fillNamed(name, value) {
    if (value === true) value = '1';
    if (value === false || value == null) value = value === false ? '' : '';
    document.querySelectorAll('[name="' + name + '"]').forEach(function (el) {
      if (el.type === 'checkbox') {
        el.checked = !!(value && value !== '0' && value !== 'false');
        el.dispatchEvent(new Event('change', { bubbles: true }));
        return;
      }
      if (el.type === 'file' || el.type === 'hidden' && name === 'job_types') return;
      setNativeValue(el, value);
    });
  }

  function prefillEdit() {
    if (!isEdit()) return;
    var id = jobIdFromPath();
    if (!id || document.documentElement.getAttribute('data-cmb-edit-filled') === id) return;
    fetch('/api/modules/custom-maker_bids/jobs/' + id, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        var d = (j && j.data) || j || {};
        document.documentElement.setAttribute('data-cmb-edit-filled', id);
        [
          'title','type','budget_min','budget_max','closes_at','rush_deadline',
          'status','audience','size_w','size_d','size_h','description',
          'contact_name','contact_phone','contact_email','zipcode','address','address_detail',
          'manager_name','manager_phone','manager_email','revision_count','revision_cost'
        ].forEach(function (k) {
          if (d[k] != null && d[k] !== '') fillNamed(k, d[k]);
        });
        ['rush_fee_enabled','schedule_premium_enabled','revision_enabled','ownership_requested',
         'ext_stl','ext_3mf','ext_obj','ext_step','ext_stp','ext_gcode','ext_fbx','ext_dwg'].forEach(function (k) {
          if (typeof d[k] !== 'undefined') fillNamed(k, d[k]);
        });
        if (d.contact_hours) {
          var parts = String(d.contact_hours).split(/[-~]/);
          if (parts[0]) fillNamed('contact_hours_from', parts[0].trim());
          if (parts[1]) fillNamed('contact_hours_to', parts[1].trim());
        }
      })
      .catch(function () {});
  }

  function ensureBidCta(viewer) {
    if (!isShow()) return;
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
        var first = form.querySelector('input,textarea');
        if (first) first.focus();
      }
    });
    if (edit && edit.parentNode) edit.parentNode.insertBefore(btn, edit.nextSibling);
    else {
      var h = document.querySelector('h1');
      if (h && h.parentNode) h.parentNode.appendChild(btn);
    }
  }

  function loadViewer() {
    if (!isShow()) return;
    var id = jobIdFromPath();
    if (!id) return;
    fetch('/api/modules/custom-maker_bids/jobs/' + id + '/viewer', { credentials: 'same-origin' })
      .then(function (r) { return r.ok ? r.json() : null; })
      .then(function (j) {
        var d = j && (j.data || j);
        ensureBidCta(d || {});
      })
      .catch(function () {});
  }

  function run() {
    prefillEdit();
    loadViewer();
  }

  run();
  document.addEventListener('DOMContentLoaded', run);
  setTimeout(run, 300);
  setTimeout(run, 1000);
  setTimeout(run, 2000);
})();
