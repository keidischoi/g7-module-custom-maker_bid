(function () {
  function add() {
    if (document.getElementById('maker-bid-nav')) return;
    var a = document.createElement('a');
    a.id = 'maker-bid-nav';
    a.href = '/maker-bid';
    a.textContent = '의뢰/입찰';
    a.style.marginLeft = '12px';
    a.style.whiteSpace = 'nowrap';
    var header =
      document.querySelector('.chd-desktop-header') ||
      document.querySelector('#desktop_header') ||
      document.querySelector('header nav') ||
      document.querySelector('header');
    if (header) header.appendChild(a);
  }
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', add);
  } else {
    add();
  }
  setTimeout(add, 400);
  setTimeout(add, 1200);
})();
