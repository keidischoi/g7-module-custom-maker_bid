(function () {
  function add() {
    var a = document.getElementById('maker-bid-nav');
    if (!a) {
      a = document.createElement('a');
      a.id = 'maker-bid-nav';
      a.href = '/maker-bid';
      a.textContent = '의뢰/입찰';
      document.body.appendChild(a);
    }
    a.style.cssText = 'position:fixed;top:14px;right:16px;z-index:99999;padding:8px 12px;border-radius:8px;background:#111;color:#fff;text-decoration:none;font-size:14px;';
  }
  add();
  document.addEventListener('DOMContentLoaded', add);
  setInterval(add, 1500);
})();
