(function () {
  /* --- boot: early dark surface + soft in-module nav (kill white flash) --- */
  var BOOT_BG = 'rgb(17 24 39)';
  var BOOT_CSS =
    'html.cmb-dark-boot,html.cmb-dark-boot body,html.dark,html.dark body,' +
    'html[data-theme="dark"],html[data-theme="dark"] body{' +
    'background-color:var(--background,var(--color-background,' + BOOT_BG + '));' +
    'color-scheme:dark}';

  function isDarkChrome() {
    var html = document.documentElement;
    if (!html) return false;
    if (html.classList.contains('dark') || html.classList.contains('dark-mode')) return true;
    if (html.getAttribute('data-theme') === 'dark') return true;
    if (html.getAttribute('data-color-mode') === 'dark') return true;
    try {
      if (localStorage.getItem('cmb-theme') === 'dark') return true;
      if (localStorage.getItem('theme') === 'dark') return true;
    } catch (e) {}
    return false;
  }

  function rememberTheme() {
    var html = document.documentElement;
    if (!html) return;
    var dark = html.classList.contains('dark') || html.classList.contains('dark-mode') ||
      html.getAttribute('data-theme') === 'dark';
    var light = html.classList.contains('light') || html.getAttribute('data-theme') === 'light';
    try {
      if (dark) localStorage.setItem('cmb-theme', 'dark');
      else if (light) localStorage.setItem('cmb-theme', 'light');
    } catch (e) {}
  }

  function injectBootCss() {
    if (document.getElementById('cmb-boot-css')) return;
    var s = document.createElement('style');
    s.id = 'cmb-boot-css';
    s.textContent = BOOT_CSS;
    var parent = document.head || document.documentElement;
    if (parent.firstChild) parent.insertBefore(s, parent.firstChild);
    else parent.appendChild(s);
  }

  function applyDarkBoot() {
    rememberTheme();
    if (!isDarkChrome()) return;
    injectBootCss();
    try { document.documentElement.classList.add('cmb-dark-boot'); } catch (e) {}
    try {
      document.documentElement.style.backgroundColor = BOOT_BG;
      if (document.body) document.body.style.backgroundColor = BOOT_BG;
    } catch (e2) {}
  }

  // Run as early as this script executes (before DOMContentLoaded when possible).
  applyDarkBoot();

  function makerBidsPath(pathname) {
    var p = String(pathname || '').replace(/\/+$/, '') || '/';
    return p === '/maker-bids' || p.indexOf('/maker-bids/') === 0;
  }

  function softGo(path) {
    function go() {
      if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
        window.G7Core.dispatch({ handler: 'navigate', params: { path: path } });
        return;
      }
      window.location.assign(path);
    }
    if (typeof document.startViewTransition === 'function') {
      try {
        document.startViewTransition(go);
        return;
      } catch (e) {}
    }
    go();
  }

  function bindSoftNav() {
    if (document.documentElement.getAttribute('data-cmb-soft-nav')) return;
    document.documentElement.setAttribute('data-cmb-soft-nav', '1');
    document.addEventListener('click', function (e) {
      if (e.defaultPrevented) return;
      if (e.button != null && e.button !== 0) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
      var t = e.target;
      var a = t && t.closest ? t.closest('a[href]') : null;
      if (!a) return;
      if (a.getAttribute('data-cmb-full-nav') === '1') return;
      if (a.classList.contains('cmb-chip') || a.getAttribute('data-cmb-status') != null || a.getAttribute('data-cmb-type') != null) return;
      if (a.closest && (a.closest('.cmb-filters') || a.closest('[data-cmb-status-chips]') || a.closest('.cmb-status-chips'))) return;
      var tgt = a.getAttribute('target');
      if (tgt && tgt !== '' && tgt !== '_self') return;
      if (a.hasAttribute('download')) return;
      var href = a.getAttribute('href');
      if (!href || href.charAt(0) === '#' || /^javascript:/i.test(href)) return;
      var url;
      try { url = new URL(href, location.origin); } catch (err) { return; }
      if (url.origin !== location.origin) return;
      if (url.pathname.indexOf('/admin/') >= 0) return;
      if (!makerBidsPath(url.pathname)) return;
      // Soft-nav maker_bids tabs, list cards, and in-module links.
      e.preventDefault();
      applyDarkBoot();
      softGo(url.pathname + url.search + url.hash);
      setTimeout(applyDarkBoot, 0);
      setTimeout(applyDarkBoot, 120);
    }, true);
  }

  bindSoftNav();
  document.addEventListener('DOMContentLoaded', function () {
    applyDarkBoot();
    bindSoftNav();
  });
  setTimeout(applyDarkBoot, 0);
  setTimeout(applyDarkBoot, 200);

  var PATH = '/maker-bids';
  var BTN_ID = 'cmb-nav-jobs';
  var CLS =
    'px-3 py-2 text-sm font-medium whitespace-nowrap cursor-pointer rounded-lg text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 inline-flex items-center gap-1.5';
  var ACTIVE =
    'px-3 py-2 text-sm font-medium whitespace-nowrap cursor-pointer rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-900 inline-flex items-center gap-1.5 cmb-nav-current';
  var TAB_IDLE = ['bg-gray-900', 'text-white', 'dark:bg-white', 'dark:text-gray-900', 'cmb-tab-current'];
  var TAB_IDLE_COLOR = ['text-gray-600', 'dark:text-gray-300', 'text-gray-500', 'dark:text-gray-400'];
  var TABS = [
    { href: '/maker-bids/new', label: '의뢰서 작성' },
    { href: '/maker-bids', label: '의뢰목록' },
    { href: '/maker-bids/bids', label: '입찰현황' },
    { href: '/maker-bids/history', label: '이력' },
    { href: '/maker-bids/disputes', label: '분쟁' },
    { href: '/maker-bids/company', label: '입찰자 등록' },
    { href: '/maker-bids/companies', label: '입찰자 목록' }
  ];
  var TAB_OFF = 'px-3 py-1.5 text-sm rounded-lg text-gray-600 dark:text-gray-300';
  var TAB_ON = 'px-3 py-1.5 text-sm rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-900 cmb-tab-current';

  function go() {
    softGo(PATH);
  }

  function currentPath() {
    return String(location.pathname || '').replace(/\/+$/, '') || '/';
  }

  function onMakerBids() {
    var p = currentPath();
    return p === PATH || p.indexOf(PATH + '/') === 0;
  }

  function currentTabHref() {
    var p = currentPath();
    if (/\/maker-bids\/new$/.test(p)) return '/maker-bids/new';
    if (/\/maker-bids\/bids$/.test(p)) return '/maker-bids/bids';
    if (/\/maker-bids\/history$/.test(p)) return '/maker-bids/history';
    if (/\/maker-bids\/disputes$/.test(p)) return '/maker-bids/disputes';
    if (/\/maker-bids\/notices$/.test(p)) return '/maker-bids/notices';
    if (/\/maker-bids\/companies$/.test(p)) return '/maker-bids/companies';
    if (/\/maker-bids\/company$/.test(p)) return '/maker-bids/company';
    if (/\/maker-bids\/\d+(\/edit)?$/.test(p)) return '';
    if (/\/maker-bids$/.test(p)) return '/maker-bids';
    return '';
  }

  function linkPath(el) {
    if (!el) return '';
    try {
      if (el.pathname) return String(el.pathname).replace(/\/+$/, '') || '/';
    } catch (e) {}
    var href = el.getAttribute && (el.getAttribute('href') || el.getAttribute('data-href'));
    if (!href) return '';
    try {
      return String(new URL(href, location.origin).pathname).replace(/\/+$/, '') || '/';
    } catch (e2) {
      return String(href).split('?')[0].replace(/\/+$/, '');
    }
  }

  function setTabCurrent(el, on) {
    var i;
    for (i = 0; i < TAB_IDLE.length; i++) el.classList.toggle(TAB_IDLE[i], on);
    for (i = 0; i < TAB_IDLE_COLOR.length; i++) {
      if (on) el.classList.remove(TAB_IDLE_COLOR[i]);
    }
    if (!on) {
      el.classList.add('text-gray-600');
      el.classList.add('dark:text-gray-300');
    }
  }

  function ensureSubNav() {
    var rows = document.querySelectorAll('[data-cmb-subnav], .cmb-maker-subnav');
    var current = currentTabHref();
    var r, row, extras, i;
    for (r = 0; r < rows.length; r++) {
      row = rows[r];
      extras = [];
      Array.prototype.slice.call(row.children).forEach(function (ch) {
        if (ch.getAttribute && ch.getAttribute('data-cmb-company-submit')) extras.push(ch);
      });
      row.innerHTML = '';
      TABS.forEach(function (tab) {
        var a = document.createElement('a');
        a.href = tab.href;
        a.textContent = tab.label;
        a.className = current === tab.href ? TAB_ON : TAB_OFF;
        row.appendChild(a);
      });
      for (i = 0; i < extras.length; i++) row.appendChild(extras[i]);
    }
  }

  function syncHeaderNav() {
    var btn = document.getElementById(BTN_ID);
    if (!btn) return;
    var on = onMakerBids();
    btn.className = on ? ACTIVE : CLS;
    if (!on && document.activeElement === btn) {
      try { btn.blur(); } catch (e) {}
    }
  }

  function syncExtNav() {
    var on = onMakerBids();
    document.querySelectorAll('.cmb-ext-user-base, .cmb-ext-home, #maker_bids_user_nav, #maker_bids_home_nav').forEach(function (el) {
      el.classList.toggle('cmb-nav-current', on);
      if (on) el.classList.add('bg-gray-900', 'text-white', 'dark:bg-white', 'dark:text-gray-900');
      else {
        el.classList.remove('bg-gray-900', 'text-white', 'dark:bg-white', 'dark:text-gray-900', 'cmb-nav-current');
        if (document.activeElement === el) {
          try { el.blur(); } catch (e) {}
        }
      }
    });
  }

  function syncSubNav() {
    var current = currentTabHref();
    var nodes = document.querySelectorAll('[data-cmb-subnav] a, [data-cmb-subnav] button, .cmb-maker-subnav a, .cmb-maker-subnav button');
    var i, el, href, on;
    for (i = 0; i < nodes.length; i++) {
      el = nodes[i];
      if (el.getAttribute && el.getAttribute('data-cmb-company-submit')) continue;
      href = linkPath(el);
      on = !!current && href === current;
      setTabCurrent(el, on);
      if (!on && document.activeElement === el) {
        try { el.blur(); } catch (e) {}
      }
    }
  }

  function syncActive() {
    ensureNavCss();
    ensureSubNav();
    syncHeaderNav();
    syncExtNav();
    syncSubNav();
  }

  function ensureNavCss() {
    if (document.getElementById('cmb-nav-css')) return;
    var s = document.createElement('style');
    s.id = 'cmb-nav-css';
    s.textContent =
      '#cmb-nav-jobs:focus,#cmb-nav-jobs:focus-visible,#cmb-nav-jobs:active{outline:none;}' +
      '#cmb-nav-jobs:not(.cmb-nav-current):focus,#cmb-nav-jobs:not(.cmb-nav-current):active{background-color:transparent;color:inherit;}' +
      '[data-cmb-subnav] a:focus,.cmb-maker-subnav a:focus{outline:none;}' +
      '[data-cmb-subnav] a:not(.cmb-tab-current):focus,.cmb-maker-subnav a:not(.cmb-tab-current):active{background-color:transparent !important;color:inherit !important;}';
    document.head.appendChild(s);
  }

  function makeBtn(label) {
    var btn = document.createElement('button');
    btn.id = BTN_ID;
    btn.type = 'button';
    btn.setAttribute('data-testid', 'nav-maker-bids');
    btn.className = onMakerBids() ? ACTIVE : CLS;
    btn.textContent = label || '의뢰/입찰';
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      go();
      setTimeout(syncActive, 0);
      setTimeout(syncActive, 200);
    });
    return btn;
  }

  function insertNav(menu) {
    if (!menu || menu.nav_js_enabled === false || menu.nav_js_enabled === 0 || menu.nav_js_enabled === '0') {
      var existing = document.getElementById(BTN_ID);
      if (existing && existing.parentNode) existing.parentNode.removeChild(existing);
      return;
    }
    if (document.getElementById(BTN_ID)) {
      syncActive();
      return;
    }
    var shop = document.querySelector('[data-testid="nav-shop"]');
    var home = document.querySelector('[data-testid="nav-home"]');
    var row = (shop && shop.parentNode) || (home && home.parentNode);
    if (!row) return;
    var btn = makeBtn(menu.nav_label);
    var where = menu.nav_insert || 'append_row';
    if (where === 'after_shop' && shop && shop.parentNode) {
      shop.parentNode.insertBefore(btn, shop.nextSibling);
      return;
    }
    if (where === 'after_home' && home && home.parentNode) {
      home.parentNode.insertBefore(btn, home.nextSibling);
      return;
    }
    if (where === 'prepend_row' && row.firstChild) {
      row.insertBefore(btn, row.firstChild);
      return;
    }
    row.appendChild(btn);
  }

  function hideExt(menu) {
    if (menu && menu.extension_user_base === false) {
      document.querySelectorAll('.cmb-ext-user-base, #maker_bids_user_nav').forEach(function (el) { el.style.display = 'none'; });
    }
    if (menu && menu.extension_home === false) {
      document.querySelectorAll('.cmb-ext-home, #maker_bids_home_nav').forEach(function (el) { el.style.display = 'none'; });
    }
  }

  var cachedNotices = {};

  function noticePage() {
    var p = location.pathname || '';
    if (/\/maker-bids\/new\/?$/.test(p)) return 'create';
    if (/\/maker-bids\/bids\/?$/.test(p)) return 'bids';
    if (/\/maker-bids\/history\/?$/.test(p)) return 'history';
    if (/\/maker-bids\/companies\/?$/.test(p)) return 'companies';
    if (/\/maker-bids\/company\/?$/.test(p)) return 'company';
    if (/\/maker-bids\/\d+\/edit\/?$/.test(p)) return 'edit';
    if (/\/maker-bids\/\d+\/?$/.test(p)) return 'show';
    if (/\/maker-bids\/?$/.test(p)) return 'list';
    return '';
  }

  function ensureNoticeCss() {
    if (document.getElementById('cmb-notice-css')) return;
    var s = document.createElement('style');
    s.id = 'cmb-notice-css';
    s.textContent =
      '.cmb-notice{border-radius:0.75rem;border:1px solid rgb(229 231 235);background:rgb(249 250 251);padding:0.75rem 1rem;font-size:0.875rem;}' +
      'html.dark .cmb-notice,.dark .cmb-notice{border-color:rgb(55 65 81);background:rgb(31 41 55 / 0.7);}';
    document.head.appendChild(s);
  }

  function applyNotice(notices) {
    if (notices && typeof notices === 'object') {
      cachedNotices = notices;
    } else {
      notices = cachedNotices;
    }
    var page = noticePage();
    var nodes = document.querySelectorAll('[data-cmb-notice]');
    if (!nodes.length) return;
    nodes.forEach(function (el) {
      var key = el.getAttribute('data-cmb-notice') || page;
      var item = notices && key ? notices[key] : null;
      var on = item && (item.enabled === true || item.enabled === 1 || item.enabled === '1');
      var html = item && item.body ? String(item.body) : '';
      if (!on || !html) {
        el.classList.add('hidden');
        el.innerHTML = '';
        return;
      }
      ensureNoticeCss();
      el.classList.remove('hidden');
      el.innerHTML = html;
    });
  }

  function apply(settings) {
    var data = settings || {};
    insertNav(data.menu || {});
    hideExt(data.menu || {});
    applyNotice(data.notices || {});
    syncActive();
  }

  function reapplyNotice() {
    applyNotice(cachedNotices);
    setTimeout(function () { applyNotice(cachedNotices); }, 0);
    setTimeout(function () { applyNotice(cachedNotices); }, 80);
    setTimeout(function () { applyNotice(cachedNotices); }, 300);
  }

  function patchHistory() {
    if (history.pushState && !history.pushState._cmbNav) {
      var push = history.pushState;
      history.pushState = function () {
        var ret = push.apply(this, arguments);
        setTimeout(syncActive, 0);
        setTimeout(syncActive, 80);
        setTimeout(syncActive, 300);
        reapplyNotice();
        return ret;
      };
      history.pushState._cmbNav = true;
    }
    if (history.replaceState && !history.replaceState._cmbNav) {
      var replace = history.replaceState;
      history.replaceState = function () {
        var ret = replace.apply(this, arguments);
        setTimeout(syncActive, 0);
        setTimeout(syncActive, 80);
        reapplyNotice();
        return ret;
      };
      history.replaceState._cmbNav = true;
    }
  }

  function bindNavSync() {
    if (document.documentElement.getAttribute('data-cmb-nav-sync')) return;
    document.documentElement.setAttribute('data-cmb-nav-sync', '1');
    patchHistory();
    window.addEventListener('popstate', function () {
      setTimeout(syncActive, 0);
      setTimeout(syncActive, 80);
      reapplyNotice();
    });
    document.addEventListener('click', function () {
      setTimeout(syncActive, 0);
      setTimeout(syncActive, 200);
      setTimeout(syncActive, 600);
      reapplyNotice();
    }, true);
  }

  function load() {
    bindNavSync();
    try {
      fetch('/api/modules/custom-maker_bids/settings', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) { apply((j && j.data) || j || {}); })
        .catch(function () {
          apply({ menu: { nav_js_enabled: true, nav_insert: 'append_row', nav_label: '의뢰/입찰' } });
        });
    } catch (e) {
      apply({ menu: { nav_js_enabled: true, nav_insert: 'append_row', nav_label: '의뢰/입찰' } });
    }
    syncActive();
  }

  load();
  document.addEventListener('DOMContentLoaded', load);
  setTimeout(load, 200);
  setTimeout(load, 800);
})();
