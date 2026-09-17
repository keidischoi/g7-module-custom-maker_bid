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


/* cmb-job-spec: render every applicable request-form field, including empty values */
(function () {
  var NONE = '해당없음';
  var PRIVATE = '비공개';
  var JOB_KEYS = ['job.data', 'job', '_data.job.data', '_data.job', 'dataSources.job.data', 'dataSources.job'];
  var VIEWER_KEYS = ['viewer.data', 'viewer', '_data.viewer.data', '_data.viewer', 'dataSources.viewer.data', 'dataSources.viewer'];

  function getState(key) {
    try {
      if (window.G7Core && window.G7Core.state && typeof window.G7Core.state.get === 'function') {
        return window.G7Core.state.get(key);
      }
    } catch (e) {}
    return null;
  }

  function unwrap(value) {
    var out = value;
    var i;
    for (i = 0; i < 4; i++) {
      if (out && out.data !== undefined && !Array.isArray(out.data) && typeof out.data === 'object') {
        out = out.data;
      } else {
        break;
      }
    }
    return out && typeof out === 'object' ? out : null;
  }

  function fromState(keys) {
    var i;
    var value;
    for (i = 0; i < keys.length; i++) {
      value = unwrap(getState(keys[i]));
      if (value && (value.id || value.title || value.type)) return value;
    }
    return null;
  }

  function isEmpty(value) {
    if (value === null || value === undefined) return true;
    if (Array.isArray(value)) return value.length === 0;
    return typeof value === 'string' && value.trim() === '';
  }

  function valueOrNone(value) {
    if (isEmpty(value)) return NONE;
    if (Array.isArray(value)) return value.join(', ') || NONE;
    return String(value);
  }

  function formatNumber(value) {
    if (value === null || value === undefined || value === '') return '';
    if (typeof value === 'string' && value.indexOf(',') >= 0) return value;
    var number = Number(String(value).replace(/[^0-9.-]/g, ''));
    if (isNaN(number)) return String(value);
    return Math.round(number).toLocaleString('ko-KR');
  }

  function money(value) {
    if (isEmpty(value)) return NONE;
    var formatted = formatNumber(value);
    if (!formatted) return NONE;
    return /원$/.test(formatted) ? formatted : formatted + '원';
  }

  window.CMB = window.CMB || {};
  window.CMB.formatNumber = formatNumber;
  window.CMB.formatMoney = money;

  function count(value) {
    if (isEmpty(value)) return NONE;
    return String(value) + '회';
  }

  function booleanLabel(value, on, off) {
    if (isEmpty(value)) return NONE;
    var enabled = value === true || value === 1 || value === '1' || value === 'true';
    return enabled ? on : off;
  }

  function fileNames(value) {
    if (!Array.isArray(value) || !value.length) return NONE;
    var names = value.map(function (file) {
      return file && (file.original_filename || file.name || file.filename);
    }).filter(function (name) { return !isEmpty(name); });
    return names.length ? names.join(', ') : NONE;
  }

  function sizeValue(job) {
    if (!isEmpty(job.size_label)) return job.size_label;
    if (Array.isArray(job.sizes) && job.sizes.length) {
      return job.sizes.map(function (row) {
        if (!row || typeof row !== 'object') return '';
        var dims = [row.w, row.d, row.h].map(function (v) { return isEmpty(v) ? '-' : v; }).join(' × ');
        return (isEmpty(row.name) ? '' : row.name + ' ') + dims + ' mm';
      }).filter(Boolean).join(', ');
    }
    return NONE;
  }

  function includesModeling(job) {
    return job.type_includes_modeling !== false;
  }

  function requiresAddress(job) {
    return job.type_requires_address !== false;
  }

  var CATALOG = [
    { section: '의뢰 정보' },
    { label: '유형', value: function (job) { return job.type_name || job.type; } },
    { label: '예산 최소', key: 'budget_min', format: money },
    { label: '예산 최대', value: function (job) { return job.budget_max == null ? job.budget : job.budget_max; }, format: money },
    { label: '마감 시각', value: function (job) { return job.closes_at || job.closes_at_local; } },
    { label: '급행비 가능', key: 'rush_fee_enabled', format: function (v) { return booleanLabel(v, '가능', '불가'); } },
    { label: '급행 적용 조건', value: function (job) { return job.rush_deadline_label || job.rush_deadline; } },
    { label: '스케줄 선점비 가능', key: 'schedule_premium_enabled', format: function (v) { return booleanLabel(v, '가능', '불가'); } },
    { label: '상태', value: function (job) { return job.status_label || job.status; } },
    { label: '공개 설정', value: function (job) { return job.audience_label || job.audience; } },
    { section: '제작 사양' },
    { label: '최종 크기', value: sizeValue, wide: true },
    { label: '제공 확장자', key: 'provided_extensions', applies: includesModeling, wide: true },
    { label: '소유권한', key: 'ownership_requested', format: function (v) { return booleanLabel(v, '요청함', '요청 안 함'); } },
    { label: '수정 횟수 적용', key: 'revision_enabled', format: function (v) { return booleanLabel(v, '적용', '미적용'); } },
    { label: '최소 수정 횟수', key: 'revision_count', format: count },
    { label: '회당 / 최대 수정비용', key: 'revision_cost', format: money },
    { label: '설명', key: 'description', wide: true, multiline: true },
    { label: '압축 파일', key: 'archives', format: fileNames, private: true, wide: true },
    { section: '개인정보' },
    { label: '주문자명 또는 업체명', key: 'contact_name', private: true },
    { label: '연락처', key: 'contact_phone', private: true },
    { label: '연락 가능시간', key: 'contact_hours', private: true },
    { label: '이메일', key: 'contact_email', private: true },
    { label: '우편번호', key: 'zipcode', private: true, applies: requiresAddress },
    { label: '주소', key: 'address', private: true, applies: requiresAddress, wide: true },
    { label: '상세 주소', key: 'address_detail', private: true, applies: requiresAddress, wide: true },
    { label: '담당자 명', key: 'manager_name', private: true },
    { label: '담당자 연락처', key: 'manager_phone', private: true },
    { label: '담당자 이메일', key: 'manager_email', private: true }
  ];
  window.CMB_JOB_SPEC_CATALOG = CATALOG;

  function privateValue(job, privacy, key) {
    if (!isEmpty(job[key])) return job[key];
    return privacy && !isEmpty(privacy[key]) ? privacy[key] : null;
  }

  function rawValue(field, job, privacy) {
    if (typeof field.value === 'function') return field.value(job, privacy);
    return field.private ? privateValue(job, privacy, field.key) : job[field.key];
  }

  function addSection(host, label) {
    var heading = document.createElement('h3');
    heading.className = 'cmb-job-card-title sm:col-span-2';
    heading.textContent = label;
    host.appendChild(heading);
  }

  function addField(host, field, value) {
    var row = document.createElement('p');
    row.className = (field.multiline ? 'cmb-show-desc whitespace-pre-wrap' : 'cmb-page-guide') + (field.wide ? ' sm:col-span-2' : '');
    var label = document.createElement('strong');
    label.className = 'font-medium text-gray-900 dark:text-white';
    label.textContent = field.label + ': ';
    var text = document.createElement('span');
    text.textContent = value;
    row.appendChild(label);
    row.appendChild(text);
    host.appendChild(row);
  }

  var lightboxImages = [];
  var lightboxIndex = 0;
  var lightboxPreviousOverflow = '';

  function imageUrl(file) {
    if (!file || typeof file !== 'object') return '';
    return String(file.download_url || file.url || file.src || '');
  }

  function isImageFile(file) {
    if (!file || typeof file !== 'object') return false;
    if (file.is_image === true || file.is_image === 1 || file.is_image === '1') return true;
    var mime = String(file.mime_type || file.mime || file.type || '').toLowerCase();
    if (mime.indexOf('image/') === 0) return true;
    var name = String(file.original_filename || file.filename || file.name || imageUrl(file)).split('?')[0].toLowerCase();
    return /\.(avif|bmp|gif|jpe?g|png|svg|webp)$/.test(name);
  }

  function jobImages(job) {
    var groups = [job.images, job.files, job.attachments, job.archives, job.deliveries];
    var seen = {};
    var out = [];
    groups.forEach(function (group) {
      if (!Array.isArray(group)) return;
      group.forEach(function (file) {
        var url = imageUrl(file);
        if (!url || !isImageFile(file) || seen[url]) return;
        seen[url] = true;
        out.push({
          url: url,
          alt: String(file.original_filename || file.filename || file.name || '의뢰 이미지')
        });
      });
    });
    return out;
  }

  function ensureLightbox() {
    var modal = document.getElementById('cmb-job-lightbox');
    if (modal) return modal;
    modal = document.createElement('div');
    modal.id = 'cmb-job-lightbox';
    modal.className = 'cmb-job-lightbox';
    modal.hidden = true;
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-label', '의뢰 이미지 보기');
    modal.innerHTML =
      '<button type="button" class="cmb-job-lightbox-close" aria-label="닫기">×</button>' +
      '<button type="button" class="cmb-job-lightbox-nav is-prev" aria-label="이전 이미지">‹</button>' +
      '<div class="cmb-job-lightbox-viewport"><div class="cmb-job-lightbox-track"></div></div>' +
      '<button type="button" class="cmb-job-lightbox-nav is-next" aria-label="다음 이미지">›</button>' +
      '<p class="cmb-job-lightbox-count" aria-live="polite"></p>';
    document.body.appendChild(modal);
    modal.querySelector('.cmb-job-lightbox-close').addEventListener('click', closeLightbox);
    modal.querySelector('.is-prev').addEventListener('click', function () { moveLightbox(-1); });
    modal.querySelector('.is-next').addEventListener('click', function () { moveLightbox(1); });
    modal.addEventListener('click', function (event) {
      if (event.target === modal) closeLightbox();
    });
    return modal;
  }

  function updateLightbox() {
    var modal = document.getElementById('cmb-job-lightbox');
    if (!modal || modal.hidden) return;
    modal.querySelector('.cmb-job-lightbox-track').style.transform = 'translate3d(-' + (lightboxIndex * 100) + '%, 0, 0)';
    modal.querySelector('.cmb-job-lightbox-count').textContent = (lightboxIndex + 1) + ' / ' + lightboxImages.length;
    modal.querySelector('.is-prev').hidden = lightboxImages.length < 2;
    modal.querySelector('.is-next').hidden = lightboxImages.length < 2;
  }

  function moveLightbox(delta) {
    if (lightboxImages.length < 2) return;
    lightboxIndex = (lightboxIndex + delta + lightboxImages.length) % lightboxImages.length;
    updateLightbox();
  }

  function openLightbox(images, index) {
    lightboxImages = images.slice();
    lightboxIndex = Math.max(0, Math.min(Number(index) || 0, lightboxImages.length - 1));
    var modal = ensureLightbox();
    var track = modal.querySelector('.cmb-job-lightbox-track');
    track.innerHTML = '';
    lightboxImages.forEach(function (image, idx) {
      var slide = document.createElement('div');
      slide.className = 'cmb-job-lightbox-slide';
      var img = document.createElement('img');
      img.src = image.url;
      img.alt = image.alt;
      img.loading = idx === lightboxIndex ? 'eager' : 'lazy';
      slide.appendChild(img);
      track.appendChild(slide);
    });
    modal.hidden = false;
    modal.classList.add('is-open');
    lightboxPreviousOverflow = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    updateLightbox();
    try { modal.querySelector('.cmb-job-lightbox-close').focus(); } catch (e) {}
  }

  function closeLightbox() {
    var modal = document.getElementById('cmb-job-lightbox');
    if (!modal || modal.hidden) return;
    modal.classList.remove('is-open');
    modal.hidden = true;
    document.body.style.overflow = lightboxPreviousOverflow;
  }

  function renderGallery(host, job) {
    if (!host) return;
    var body = host.closest ? host.closest('.cmb-job-spec-body') : null;
    var images = jobImages(job);
    host.innerHTML = '';
    if (!images.length) {
      host.hidden = true;
      if (body) body.classList.remove('has-gallery');
      return;
    }
    host.hidden = false;
    if (body) body.classList.add('has-gallery');
    var button = document.createElement('button');
    button.type = 'button';
    button.className = 'cmb-job-spec-gallery-button';
    button.setAttribute('aria-label', '의뢰 이미지 ' + images.length + '장 보기');
    var image = document.createElement('img');
    image.src = images[0].url;
    image.alt = images[0].alt;
    image.loading = 'lazy';
    button.appendChild(image);
    if (images.length > 1) {
      var badge = document.createElement('span');
      badge.className = 'cmb-job-spec-gallery-count';
      badge.textContent = '+' + (images.length - 1);
      button.appendChild(badge);
    }
    button.addEventListener('click', function () { openLightbox(images, 0); });
    host.appendChild(button);
  }

  if (!window.__cmbJobLightboxKeys) {
    window.__cmbJobLightboxKeys = true;
    document.addEventListener('keydown', function (event) {
      var modal = document.getElementById('cmb-job-lightbox');
      if (!modal || modal.hidden) return;
      if (event.key === 'Escape') closeLightbox();
      if (event.key === 'ArrowLeft') moveLightbox(-1);
      if (event.key === 'ArrowRight') moveLightbox(1);
    });
  }

  function signatureOf(value) {
    var text;
    var hash = 5381;
    var i;
    try { text = JSON.stringify(value); } catch (e) { text = String(value); }
    for (i = 0; i < text.length; i++) hash = ((hash << 5) + hash) ^ text.charCodeAt(i);
    return String(hash >>> 0);
  }

  function render(host, job, viewer) {
    var admin = /^\/admin\/maker-bids\/jobs\/\d+\/?$/.test(String(location.pathname || ''));
    var canViewPrivate = admin || job.privacy_visible === true || !!(viewer && viewer.can_view_privacy);
    var privacy = viewer && viewer.privacy && typeof viewer.privacy === 'object' ? viewer.privacy : {};
    var signature = signatureOf([job.id, job.updated_at, canViewPrivate, job, privacy]);
    if (host.getAttribute('data-cmb-job-spec-signature') === signature) return;
    host.innerHTML = '';
    CATALOG.forEach(function (field) {
      if (field.section) {
        addSection(host, field.section);
        return;
      }
      if (field.applies && !field.applies(job)) return;
      var value = field.private && !canViewPrivate ? PRIVATE : rawValue(field, job, privacy);
      if (!(field.private && !canViewPrivate)) {
        value = field.format ? field.format(value) : valueOrNone(value);
      }
      addField(host, field, value);
    });
    if (!canViewPrivate) {
      var note = document.createElement('p');
      note.className = 'cmb-hint sm:col-span-2';
      note.textContent = '연락처·배송지 등 개인정보는 의뢰 본인, 관리자, 낙찰 완료 후에만 확인할 수 있습니다.';
      host.appendChild(note);
    }
    var body = host.closest ? host.closest('.cmb-job-spec-body') : null;
    renderGallery(body && body.querySelector('[data-cmb-job-gallery]'), job);
    host.setAttribute('data-cmb-job-spec-signature', signature);
  }

  function scan() {
    var job = fromState(JOB_KEYS);
    if (!job) return;
    var viewer = fromState(VIEWER_KEYS) || unwrap(getState('viewer.data')) || unwrap(getState('viewer')) || {};
    document.querySelectorAll('[data-cmb-job-spec]').forEach(function (host) {
      render(host, job, viewer);
    });
  }

  scan();
  document.addEventListener('DOMContentLoaded', scan);
  setTimeout(scan, 200);
  setTimeout(scan, 800);
  setTimeout(scan, 1600);
  setTimeout(scan, 3200);
  if (!window.__cmbJobSpecObs) {
    window.__cmbJobSpecObs = new MutationObserver(scan);
    try { window.__cmbJobSpecObs.observe(document.documentElement, { childList: true, subtree: true }); } catch (e) {}
  }
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
  var FORM_CSS = '/api/modules/custom-maker_bids/assets/form.css?v=0.10.8';
  var ITEM_RE = /(^|\s)(cmb-job-card|cmb-bid-card|cmb-company-card|cmb-list-item|cmb-section-card|cmb-empty|cmb-pager)(\s|$)/;

  function ensureFormCss() {
    var existing = document.querySelector('link[href*="custom-maker_bids/assets/form.css"]');
    if (existing) {
      if (existing.href && existing.href.indexOf('v=0.10.8') < 0) {
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

/* cmb-export: printable 의뢰서/견적서 HTML → print-to-PDF */
(function () {
  function jobIdFromWorkPath() {
    var m = String(location.pathname || '').match(/\/maker-bids\/(\d+)(?:\/work)?\/?$/);
    return m ? m[1] : '';
  }
  function openHtml(html) {
    var w = window.open('', '_blank');
    if (!w) {
      if (window.G7Core && window.G7Core.dispatch) {
        window.G7Core.dispatch({ handler: 'toast', params: { type: 'warning', message: '팝업이 차단되었습니다. 팝업을 허용해 주세요.' } });
      }
      return;
    }
    w.document.open();
    w.document.write(html);
    w.document.close();
  }
  function fetchExport(doc) {
    var id = jobIdFromWorkPath();
    if (!id) return;
    var url = '/api/modules/custom-maker_bids/jobs/' + id + '/export?doc=' + encodeURIComponent(doc || 'all');
    function handlePayload(payload) {
      var d = payload && payload.data !== undefined ? payload.data : payload;
      if (!d) return;
      if (d.html) {
        openHtml(d.html);
        return;
      }
      if (d.documents && d.documents[0] && d.documents[0].html) {
        openHtml(d.documents[0].html);
      }
    }
    if (window.G7Core && window.G7Core.api && typeof window.G7Core.api.get === 'function') {
      window.G7Core.api.get(url).then(handlePayload).catch(function () {
        if (window.G7Core && window.G7Core.dispatch) {
          window.G7Core.dispatch({ handler: 'toast', params: { type: 'error', message: '문서를 불러오지 못했습니다.' } });
        }
      });
      return;
    }
    fetch(url, { credentials: 'include', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.json(); })
      .then(handlePayload)
      .catch(function () {});
  }
  function onClick(e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-cmb-export]') : null;
    if (!btn) return;
    e.preventDefault();
    fetchExport(btn.getAttribute('data-cmb-export') || 'all');
  }
  if (!window.__cmbExportBound) {
    window.__cmbExportBound = true;
    document.addEventListener('click', onClick, true);
  }
})();
