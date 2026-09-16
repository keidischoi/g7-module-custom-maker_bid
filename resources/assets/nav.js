(function () {
  var PATH = '/maker-bids';
  var BTN_ID = 'cmb-nav-jobs';
  var CLS =
    'px-3 py-2 text-sm font-medium whitespace-nowrap cursor-pointer rounded-lg text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 inline-flex items-center gap-1.5';
  var ACTIVE =
    'px-3 py-2 text-sm font-medium whitespace-nowrap cursor-pointer rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-900 inline-flex items-center gap-1.5 cmb-nav-current';
  var TAB_IDLE = ['bg-gray-900', 'text-white', 'dark:bg-white', 'dark:text-gray-900', 'cmb-tab-current'];
  var TAB_IDLE_COLOR = ['text-gray-600', 'dark:text-gray-300', 'text-gray-500', 'dark:text-gray-400'];

  function go() {
    if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
      window.G7Core.dispatch({ handler: 'navigate', params: { path: PATH } });
      return;
    }
    window.location.href = PATH;
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
    if (/\/maker-bids\/new$/.test(p)) {
      return '/maker-bids/new';
    }
    if (/\/maker-bids\/bids$/.test(p)) {
      return '/maker-bids/bids';
    }
    if (/\/maker-bids\/history$/.test(p)) {
      return '/maker-bids/history';
    }
    if (/\/maker-bids\/company$/.test(p)) {
      return '/maker-bids/company';
    }
    if (/\/maker-bids\/\d+(\/edit)?$/.test(p)) {
      return '';
    }
    if (/\/maker-bids$/.test(p)) {
      return '/maker-bids';
    }
    return '';
  }

  function linkPath(el) {
    if (!el) {
      return '';
    }
    try {
      if (el.pathname) {
        return String(el.pathname).replace(/\/+$/, '') || '/';
      }
    } catch (e) {}
    var href = el.getAttribute && (el.getAttribute('href') || el.getAttribute('data-href'));
    if (!href) {
      return '';
    }
    try {
      return String(new URL(href, location.origin).pathname).replace(/\/+$/, '') || '/';
    } catch (e2) {
      return String(href).split('?')[0].replace(/\/+$/, '');
    }
  }

  function setTabCurrent(el, on) {
    var i;
    for (i = 0; i < TAB_IDLE.length; i++) {
      el.classList.toggle(TAB_IDLE[i], on);
    }
    for (i = 0; i < TAB_IDLE_COLOR.length; i++) {
      if (on) {
        el.classList.remove(TAB_IDLE_COLOR[i]);
      }
    }
    if (!on) {
      el.classList.add('text-gray-600');
      el.classList.add('dark:text-gray-300');
    }
  }

  function syncHeaderNav() {
    var btn = document.getElementById(BTN_ID);
    if (!btn) {
      return;
    }
    var on = onMakerBids();
    btn.className = on ? ACTIVE : CLS;
    if (!on && document.activeElement === btn) {
      try {
        btn.blur();
      } catch (e) {}
    }
  }

  function syncExtNav() {
    var on = onMakerBids();
    document.querySelectorAll('.cmb-ext-user-base, .cmb-ext-home, #maker_bids_user_nav, #maker_bids_home_nav').forEach(function (el) {
      el.classList.toggle('cmb-nav-current', on);
      if (on) {
        el.classList.add('bg-gray-900', 'text-white', 'dark:bg-white', 'dark:text-gray-900');
      } else {
        el.classList.remove('bg-gray-900', 'text-white', 'dark:bg-white', 'dark:text-gray-900', 'cmb-nav-current');
        if (document.activeElement === el) {
          try {
            el.blur();
          } catch (e) {}
        }
      }
    });
  }

  function syncSubNav() {
    var current = currentTabHref();
    var nodes = document.querySelectorAll('[data-cmb-subnav] a, [data-cmb-subnav] button, .cmb-maker-subnav a, .cmb-maker-subnav button');
    var i;
    var el;
    var href;
    var on;
    for (i = 0; i < nodes.length; i++) {
      el = nodes[i];
      if (el.getAttribute && el.getAttribute('data-cmb-company-submit')) {
        continue;
      }
      href = linkPath(el);
      on = !!current && href === current;
      setTabCurrent(el, on);
      if (!on && document.activeElement === el) {
        try {
          el.blur();
        } catch (e) {}
      }
    }
  }

  function syncActive() {
    ensureNavCss();
    syncHeaderNav();
    syncExtNav();
    syncSubNav();
  }

  function ensureNavCss() {
    if (document.getElementById('cmb-nav-css')) {
      return;
    }
    var s = document.createElement('style');
    s.id = 'cmb-nav-css';
    s.textContent =
      '#cmb-nav-jobs:focus,#cmb-nav-jobs:focus-visible,#cmb-nav-jobs:active{' +
      'outline:none;}' +
      '#cmb-nav-jobs:not(.cmb-nav-current):focus,#cmb-nav-jobs:not(.cmb-nav-current):focus-visible,#cmb-nav-jobs:not(.cmb-nav-current):active' +
      '{background-color:transparent;color:inherit;}' +
      'html.dark #cmb-nav-jobs:not(.cmb-nav-current):focus,html.dark #cmb-nav-jobs:not(.cmb-nav-current):active,' +
      '.dark #cmb-nav-jobs:not(.cmb-nav-current):focus,.dark #cmb-nav-jobs:not(.cmb-nav-current):active{' +
      'background-color:transparent;color:inherit;}' +
      '[data-cmb-subnav] a:focus,[data-cmb-subnav] a:focus-visible,[data-cmb-subnav] a:active,' +
      '.cmb-maker-subnav a:focus,.cmb-maker-subnav a:focus-visible,.cmb-maker-subnav a:active{outline:none;}' +
      '[data-cmb-subnav] a:not(.cmb-tab-current):focus,[data-cmb-subnav] a:not(.cmb-tab-current):focus-visible,' +
      '[data-cmb-subnav] a:not(.cmb-tab-current):active,' +
      '.cmb-maker-subnav a:not(.cmb-tab-current):focus,.cmb-maker-subnav a:not(.cmb-tab-current):active{' +
      'background-color:transparent !important;color:inherit !important;box-shadow:none !important;}' +
      '.cmb-ext-user-base:not(.cmb-nav-current):focus,.cmb-ext-home:not(.cmb-nav-current):focus,' +
      '#maker_bids_user_nav:not(.cmb-nav-current):focus,#maker_bids_home_nav:not(.cmb-nav-current):focus,' +
      '.cmb-ext-user-base:not(.cmb-nav-current):active,.cmb-ext-home:not(.cmb-nav-current):active{' +
      'background-color:transparent !important;color:inherit !important;}';
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
      document.querySelectorAll('.cmb-ext-user-base, #maker_bids_user_nav').forEach(function (el) {
        el.style.display = 'none';
      });
    }
    if (menu && menu.extension_home === false) {
      document.querySelectorAll('.cmb-ext-home, #maker_bids_home_nav').forEach(function (el) {
        el.style.display = 'none';
      });
    }
  }

  function noticePage() {
    var p = location.pathname || '';
    if (/\/maker-bids\/new\/?$/.test(p)) return 'create';
    if (/\/maker-bids\/bids\/?$/.test(p)) return 'bids';
    if (/\/maker-bids\/history\/?$/.test(p)) return 'history';
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
      '.cmb-notice{border-radius:0.75rem;border:1px solid rgb(229 231 235);background:rgb(249 250 251);padding:0.75rem 1rem;font-size:0.875rem;line-height:1.5;color:rgb(55 65 81);}' +
      'html.dark .cmb-notice,.dark .cmb-notice{border-color:rgb(55 65 81);background:rgb(31 41 55 / 0.7);color:rgb(229 231 235);}' +
      '.cmb-notice a{text-decoration:underline;}';
    document.head.appendChild(s);
  }

  function applyNotice(notices) {
    var page = noticePage();
    var nodes = document.querySelectorAll('[data-cmb-notice]');
    if (!nodes.length) return;
    var n = notices && page ? notices[page] : null;
    var enabled = n && (n.enabled === true || n.enabled === 1 || n.enabled === '1');
    var body = n && n.body ? String(n.body) : '';
    nodes.forEach(function (el) {
      var key = el.getAttribute('data-cmb-notice') || page;
      var item = notices && key ? notices[key] : n;
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

  function patchHistory() {
    if (history.pushState && !history.pushState._cmbNav) {
      var push = history.pushState;
      history.pushState = function () {
        var ret = push.apply(this, arguments);
        setTimeout(syncActive, 0);
        setTimeout(syncActive, 80);
        setTimeout(syncActive, 300);
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
        return ret;
      };
      history.replaceState._cmbNav = true;
    }
  }

  function bindNavSync() {
    if (document.documentElement.getAttribute('data-cmb-nav-sync')) {
      return;
    }
    document.documentElement.setAttribute('data-cmb-nav-sync', '1');
    patchHistory();
    window.addEventListener('popstate', function () {
      setTimeout(syncActive, 0);
      setTimeout(syncActive, 80);
    });
    document.addEventListener(
      'click',
      function () {
        setTimeout(syncActive, 0);
        setTimeout(syncActive, 200);
        setTimeout(syncActive, 600);
      },
      true
    );
  }

  function load() {
    bindNavSync();
    try {
      fetch('/api/modules/custom-maker_bids/settings', { credentials: 'same-origin' })
        .then(function (r) {
          return r.json();
        })
        .then(function (j) {
          apply((j && j.data) || j || {});
        })
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
