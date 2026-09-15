(function () {
  function add() {
    var existing = document.getElementById('maker-bid-nav');
    var navs = document.querySelectorAll('nav');
    var nav = null;
    for (var i = 0; i < navs.length; i++) {
      var html = navs[i].innerHTML || '';
      if (html.indexOf('/shop/products') !== -1 || html.indexOf('/boards/popular') !== -1) {
        nav = navs[i];
        break;
      }
    }
    if (!nav && navs.length) nav = navs[0];
    if (!nav) return;

    if (existing && existing.parentNode !== nav) {
      existing.parentNode.removeChild(existing);
      existing = null;
    }
    if (existing) {
      nav.appendChild(existing);
      return;
    }

    var sample = nav.querySelector('a');
    var a = document.createElement('a');
    a.id = 'maker-bid-nav';
    a.href = '/maker-bid';
    a.textContent = '의뢰/입찰';
    if (sample && sample.className) a.className = sample.className;
    nav.appendChild(a);
  }
  add();
  document.addEventListener('DOMContentLoaded', add);
  setTimeout(add, 300);
  setTimeout(add, 1000);
})();
