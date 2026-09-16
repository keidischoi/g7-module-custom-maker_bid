(function () {
  var CLS = 'cmb-order-field rounded-lg border border-gray-300 dark:border-gray-600 bg-background dark:bg-gray-900 px-3 py-2.5 text-sm';
  var ON_APPROVE = 'background:#059669;color:#fff;border-color:#059669';
  var ON_HOLD = 'background:#d97706;color:#fff;border-color:#d97706';
  var OFF = 'background:transparent;color:inherit';

  function statusFromText(text) {
    text = String(text || '');
    if (text.indexOf('보류') >= 0) return 'hold';
    if (text.indexOf('견적') >= 0 || text.indexOf('open') >= 0) return 'quote_request';
    return '';
  }

  function paintBtn(btn, on, styleOn) {
    if (!btn) return;
    btn.setAttribute('style', on ? styleOn : OFF);
  }

  function buttonsIn(root) {
    var all = root.querySelectorAll('button');
    var approve = null;
    var hold = null;
    all.forEach(function (b) {
      var t = (b.textContent || '').trim();
      if (t === '승인') approve = b;
      if (t === '보류') hold = b;
    });
    return { approve: approve, hold: hold };
  }

  function paintStatusButtons() {
    document.querySelectorAll('.cmb-admin-row').forEach(function (row) {
      var meta = row.querySelector('.cmb-admin-muted, .cmb-admin-meta');
      var st = statusFromText(meta && meta.textContent);
      var btns = buttonsIn(row);
      paintBtn(btns.approve, st === 'quote_request', ON_APPROVE);
      paintBtn(btns.hold, st === 'hold', ON_HOLD);
    });
    var bar = document.querySelector('.cmb-admin-toolbar');
    if (bar) {
      var meta = document.querySelector('.cmb-admin-meta');
      var st = statusFromText(meta && meta.textContent);
      var btns = buttonsIn(bar);
      if (!btns.approve && btns.hold) {
        var a = document.createElement('button');
        a.type = 'button';
        a.textContent = '승인';
        a.className = 'cmb-btn-approve px-3 py-1.5 text-sm rounded-lg border';
        a.addEventListener('click', function () {
          var m = location.pathname.match(/jobs\/(\d+)/);
          if (!m) return;
          if (!confirm('승인하여 견적요청(open)으로 공개할까요?')) return;
          fetch('/api/modules/custom-maker_bids/admin/jobs/' + m[1] + '/approve', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
          }).then(function () { location.reload(); });
        });
        bar.insertBefore(a, btns.hold);
        btns.approve = a;
      }
      paintBtn(btns.approve, st === 'quote_request', ON_APPROVE);
      paintBtn(btns.hold, st === 'hold', ON_HOLD);
    }
  }

  function parseSizes(raw) {
    if (Array.isArray(raw) && raw.length) {
      return raw.map(function (r) {
        return { name: r && r.name != null ? String(r.name) : '', w: r && r.w != null ? String(r.w) : '', d: r && r.d != null ? String(r.d) : '', h: r && r.h != null ? String(r.h) : '' };
      });
    }
    if (typeof raw === 'string' && raw.trim()) {
      try { return parseSizes(JSON.parse(raw)); } catch (e) {}
    }
    return [{ name: '', w: '', d: '', h: '' }];
  }

  function makeRow(item, canRemove) {
    var wrap = document.createElement('div');
    wrap.setAttribute('data-cmb-size-row', '1');
    wrap.className = 'cmb-size-row flex flex-wrap items-center gap-2';
    wrap.innerHTML =
      '<input data-cmb-size-name type="text" maxlength="80" placeholder="이름" class="' + CLS + ' cmb-size-name">' +
      '<input data-cmb-size-w type="number" min="0" placeholder="W" class="' + CLS + ' cmb-size-dim">' +
      '<span class="text-gray-400">×</span>' +
      '<input data-cmb-size-d type="number" min="0" placeholder="D" class="' + CLS + ' cmb-size-dim">' +
      '<span class="text-gray-400">×</span>' +
      '<input data-cmb-size-h type="number" min="0" placeholder="H" class="' + CLS + ' cmb-size-dim">' +
      '<button type="button" data-cmb-size-remove class="cmb-size-remove shrink-0 px-2.5 py-2 text-sm rounded-lg border">삭제</button>';
    wrap.querySelector('[data-cmb-size-name]').value = item.name || '';
    wrap.querySelector('[data-cmb-size-w]').value = item.w || '';
    wrap.querySelector('[data-cmb-size-d]').value = item.d || '';
    wrap.querySelector('[data-cmb-size-h]').value = item.h || '';
    if (!canRemove) wrap.querySelector('[data-cmb-size-remove]').style.visibility = 'hidden';
    return wrap;
  }

  function collect(list) {
    var rows = [];
    list.querySelectorAll('[data-cmb-size-row]').forEach(function (row) {
      rows.push({
        name: row.querySelector('[data-cmb-size-name]').value,
        w: row.querySelector('[data-cmb-size-w]').value === '' ? null : Number(row.querySelector('[data-cmb-size-w]').value),
        d: row.querySelector('[data-cmb-size-d]').value === '' ? null : Number(row.querySelector('[data-cmb-size-d]').value),
        h: row.querySelector('[data-cmb-size-h]').value === '' ? null : Number(row.querySelector('[data-cmb-size-h]').value)
      });
    });
    return rows;
  }

  function enhanceSizes() {
    var ta = document.querySelector('textarea[name="sizes_json"]');
    if (!ta || ta.getAttribute('data-cmb-size-ui')) return;
    ta.setAttribute('data-cmb-size-ui', '1');
    ta.style.display = 'none';
    ['size_w', 'size_d', 'size_h'].forEach(function (n) {
      document.querySelectorAll('[name="' + n + '"]').forEach(function (el) {
        (el.closest('div') || el).style.display = 'none';
      });
    });
    var items = parseSizes(ta.value || ta.getAttribute('placeholder'));
    var root = document.createElement('div');
    root.className = 'cmb-sizes space-y-2';
    var list = document.createElement('div');
    list.className = 'cmb-sizes-list space-y-2';
    items.forEach(function (it) { list.appendChild(makeRow(it, items.length > 1)); });
    var add = document.createElement('button');
    add.type = 'button';
    add.textContent = '추가';
    add.className = 'px-3 py-1.5 text-sm rounded-lg border';
    root.appendChild(list);
    root.appendChild(add);
    ta.parentNode.insertBefore(root, ta);
    function sync() {
      ta.value = JSON.stringify(collect(list));
      ta.dispatchEvent(new Event('input', { bubbles: true }));
    }
    add.addEventListener('click', function (e) { e.preventDefault(); list.appendChild(makeRow({ name: '', w: '', d: '', h: '' }, true)); sync(); });
    list.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('[data-cmb-size-remove]');
      if (!btn || list.querySelectorAll('[data-cmb-size-row]').length <= 1) return;
      e.preventDefault();
      btn.closest('[data-cmb-size-row]').remove();
      sync();
    });
    list.addEventListener('input', sync);
    sync();
  }

  function start() {
    if (!document.querySelector('.cmb-admin')) return;
    enhanceSizes();
    paintStatusButtons();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
  setTimeout(start, 400);
  setTimeout(paintStatusButtons, 1200);
})();
