(function () {
  var LABEL = '의뢰/입찰';
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

  function makeBtn() {
    var btn = document.createElement('button');
    btn.id = BTN_ID;
    btn.type = 'button';
    btn.setAttribute('data-testid', 'nav-maker-bid');
    btn.className = (location.pathname || '').indexOf('/maker-bid') === 0 ? ACTIVE : CLS;
    btn.textContent = LABEL;
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      go();
    });
    return btn;
  }

  function insert() {
    if (document.getElementById(BTN_ID)) return;
    var shop = document.querySelector('[data-testid="nav-shop"]');
    var home = document.querySelector('[data-testid="nav-home"]');
    var row = (shop && shop.parentNode) || (home && home.parentNode);
    if (!row) return;
    row.appendChild(makeBtn());
  }

  insert();
  document.addEventListener('DOMContentLoaded', insert);
  setTimeout(insert, 200);
  setTimeout(insert, 800);
})();
