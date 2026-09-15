(function () {
  function add() {
    var header = document.getElementById('desktop_header');
    if (!header) return;
    var nav = header.querySelector('nav');
    if (!nav) return;

    var existing = document.getElementById('maker-bid-nav');
    if (existing && existing.parentNode !== nav) {
      existing.parentNode.removeChild(existing);
      existing = null;
    }
    if (!existing) {
      var sample = nav.querySelector('a:last-child') || nav.querySelector('a');
      existing = document.createElement('a');
      existing.id = 'maker-bid-nav';
      existing.href = '/maker-bid';
      existing.textContent = '의뢰/입찰';
      if (sample && sample.className) existing.className = sample.className;
    }
    if (nav.lastElementChild !== existing) {
      nav.appendChild(existing);
    }
  }
  add();
  document.addEventListener('DOMContentLoaded', add);
  setTimeout(add, 200);
  setTimeout(add, 800);
  setTimeout(add, 2000);
})();
