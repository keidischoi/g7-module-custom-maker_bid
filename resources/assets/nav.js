(function () {
  var PATH = '/maker-bid';
  var BTN_ID = 'cmb-nav-jobs';
  var CLS =
    'px-3 py-2 text-sm font-medium whitespace-nowrap cursor-pointer rounded-lg text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800 inline-flex items-center gap-1.5';
  var ACTIVE =
    'px-3 py-2 text-sm font-medium whitespace-nowrap cursor-pointer rounded-lg bg-gray-900 text-white dark:bg-white dark:text-gray-900 inline-flex items-center gap-1.5';

  function go() {
    if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
      window.G7Core.dispatch({ handler: 'navigate', params: { path: PATH } });
      return;
    }
    window.location.href = PATH;
  }

  function makeBtn(label) {
    var btn = document.createElement('button');
    btn.id = BTN_ID;
    btn.type = 'button';
    btn.setAttribute('data-testid', 'nav-maker-bid');
    btn.className = (location.pathname || '').indexOf('/maker-bid') === 0 ? ACTIVE : CLS;
    btn.textContent = label || '의뢰/입찰';
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      go();
    });
    return btn;
  }

  function insertNav(menu) {
    if (!menu || menu.nav_js_enabled === false || menu.nav_js_enabled === 0 || menu.nav_js_enabled === '0') {
      var existing = document.getElementById(BTN_ID);
      if (existing && existing.parentNode) existing.parentNode.removeChild(existing);
      return;
    }
    if (document.getElementById(BTN_ID)) return;
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
    if (/\/maker-bid\/new\/?$/.test(p)) return 'create';
    if (/\/maker-bid\/bids\/?$/.test(p)) return 'bids';
    if (/\/maker-bid\/history\/?$/.test(p)) return 'history';
    if (/\/maker-bid\/company\/?$/.test(p)) return 'company';
    if (/\/maker-bid\/\d+\/edit\/?$/.test(p)) return 'edit';
    if (/\/maker-bid\/\d+\/?$/.test(p)) return 'show';
    if (/\/maker-bid\/?$/.test(p)) return 'list';
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
  }

  function load() {
    try {
      fetch('/api/modules/custom-maker_bid/settings', { credentials: 'same-origin' })
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
  }

  load();
  document.addEventListener('DOMContentLoaded', load);
  setTimeout(load, 200);
  setTimeout(load, 800);
})();

