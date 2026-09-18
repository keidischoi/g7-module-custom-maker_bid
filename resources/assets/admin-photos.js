(function () {
  if (window.__cmbAdminPhotos) return;
  window.__cmbAdminPhotos = true;

  function isImgName(s) {
    return /\.(jpe?g|png|gif|webp)$/i.test(String(s || ''));
  }

  function show() {
    if (!document.querySelector('.cmb-admin')) return;
    document.querySelectorAll('a[href], img, span, p, div, td').forEach(function (el) {
      if (el.getAttribute && el.getAttribute('data-cmb-photo')) return;
      var href = el.getAttribute && el.getAttribute('href');
      var text = (el.textContent || '').trim();
      var src = href || '';
      if (el.tagName === 'IMG') return;
      if (!isImgName(href || '') && !isImgName(text)) return;
      if (el.querySelector && el.querySelector('img')) return;
      if (text.length > 80) return;
      var url = href || '';
      if (!url && isImgName(text)) {
        var a = el.querySelector && el.querySelector('a[href]');
        url = a ? a.getAttribute('href') : '';
      }
      if (!url) return;
      var img = document.createElement('img');
      img.src = url;
      img.alt = text || 'image';
      img.style.cssText = 'max-width:220px;max-height:160px;object-fit:cover;border-radius:10px;display:block;margin:6px 0;border:1px solid rgba(255,255,255,0.12)';
      img.onerror = function () { this.style.display = 'none'; };
      el.setAttribute('data-cmb-photo', '1');
      el.insertBefore(img, el.firstChild);
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', show);
  else show();
  setTimeout(show, 500);
  setTimeout(show, 1400);
})();
