(function () {
  function findRow() {
    var nodes = document.querySelectorAll('a, button, span, div');
    for (var i = 0; i < nodes.length; i++) {
      var t = (nodes[i].textContent || '').replace(/\s+/g, '');
      if (t === '자유게시판' || t === '공지사항' || t === '쇼핑') {
        return nodes[i].parentElement;
      }
    }
    var header = document.getElementById('desktop_header');
    if (header && header.querySelector('nav')) return header.querySelector('nav');
    return header || null;
  }

  function add() {
    var row = findRow();
    if (!row) return;
    var existing = document.getElementById('maker-bid-nav');
    if (existing && existing.parentNode !== row) {
      existing.parentNode.removeChild(existing);
      existing = null;
    }
    if (!existing) {
      existing = document.createElement('a');
      existing.id = 'maker-bid-nav';
      existing.href = '/maker-bid';
      existing.textContent = '의뢰/입찰';
      var sample = row.querySelector('a:last-of-type') || row.querySelector('a');
      if (sample && sample.className) existing.className = sample.className;
      existing.style.color = 'inherit';
      existing.style.whiteSpace = 'nowrap';
    }
    if (row.lastElementChild !== existing) row.appendChild(existing);
  }

  add();
  document.addEventListener('DOMContentLoaded', add);
  setTimeout(add, 200);
  setTimeout(add, 800);
  setTimeout(add, 2000);
  if (window.MutationObserver) {
    var ob = new MutationObserver(function () { add(); });
    ob.observe(document.body, { childList: true, subtree: true });
  }
})();
