(function () {
  var CLS = 'cmb-order-field rounded-lg border border-gray-300 dark:border-gray-600 bg-background px-3 py-2.5 text-sm';
  var ON_APPROVE = 'background:#059669;color:#fff;border-color:#059669';
  var ON_HOLD = 'background:#d97706;color:#fff;border-color:#d97706';
  var ON_REJECT = 'background:#dc2626;color:#fff;border-color:#dc2626';
  var OFF = 'background:transparent;color:inherit';

  function statusFromText(text) {
    text = String(text || '');
    if (text.indexOf('거절') >= 0) return 'rejected';
    if (text.indexOf('보류') >= 0) return 'hold';
    if (text.indexOf('견적') >= 0 || /\bopen\b/i.test(text)) return 'quote_request';
    if (text.indexOf('승인') >= 0) return 'approved';
    return '';
  }

  function paintBtn(btn, on, styleOn) {
    if (!btn) return;
    btn.setAttribute('style', on ? styleOn : OFF);
  }

  function buttonsIn(root) {
    var out = { approve: null, hold: null, reject: null };
    root.querySelectorAll('button').forEach(function (b) {
      var t = (b.textContent || '').trim();
      if (t === '승인') out.approve = b;
      if (t === '보류') out.hold = b;
      if (t === '거절') out.reject = b;
    });
    return out;
  }

  function paintGroup(root, st) {
    var btns = buttonsIn(root);
    paintBtn(btns.approve, st === 'quote_request' || st === 'approved', ON_APPROVE);
    paintBtn(btns.hold, st === 'hold', ON_HOLD);
    paintBtn(btns.reject, st === 'rejected', ON_REJECT);
  }

  function companyIdFromEdit() {
    var h = document.body.innerText.match(/선택 업체 관리 \(#(\d+)/);
    return h ? h[1] : '';
  }

  function postCompany(id, action) {
    if (!id) return;
    fetch('/api/modules/custom-maker_bids/admin/companies/' + id + '/' + action, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function () { location.reload(); });
  }

  function ensureCompanyEditButtons() {
    var save = null;
    document.querySelectorAll('button').forEach(function (b) {
      if ((b.textContent || '').trim() === '선택 업체 저장') save = b;
    });
    if (!save || save.getAttribute('data-cmb-co-btns')) return;
    save.setAttribute('data-cmb-co-btns', '1');
    var wrap = save.parentElement || save;
    function add(label, action, confirmMsg) {
      var b = document.createElement('button');
      b.type = 'button';
      b.textContent = label;
      b.className = 'px-3 py-1.5 text-sm rounded-lg border mr-2';
      b.addEventListener('click', function () {
        var id = companyIdFromEdit();
        if (!id) return alert('먼저 목록에서 업체를 불러오세요.');
        if (!confirm(confirmMsg)) return;
        postCompany(id, action);
      });
      wrap.insertBefore(b, save);
      return b;
    }
    add('승인', 'approve', '이 업체를 승인할까요?');
    add('보류', 'hold', '이 업체를 보류할까요?');
    add('거절', 'reject', '이 업체를 거절할까요?');
  }

  function paintStatusButtons() {
    document.querySelectorAll('.cmb-admin-row').forEach(function (row) {
      var meta = row.querySelector('.cmb-admin-muted, .cmb-admin-meta');
      paintGroup(row, statusFromText(meta && meta.textContent));
    });
    var bar = document.querySelector('.cmb-admin-toolbar');
    if (bar) {
      var meta = document.querySelector('.cmb-admin-meta');
      paintGroup(bar, statusFromText(meta && meta.textContent));
    }
    ensureCompanyEditButtons();
    var editTitle = Array.prototype.find.call(document.querySelectorAll('h2,h1,p'), function (el) {
      return /선택 업체/.test(el.textContent || '');
    });
    if (editTitle) {
      var card = editTitle.closest('.cmb-admin-card') || editTitle.parentElement;
      var st = '';
      document.querySelectorAll('.cmb-admin-row').forEach(function (row) {
        if (row.querySelector('[href*="companies"], button')) {
          /* keep last loaded row if marked */
        }
      });
      var selected = companyIdFromEdit();
      document.querySelectorAll('.cmb-admin-row').forEach(function (row) {
        var title = row.querySelector('.cmb-admin-row-title, a');
        if (selected && title && title.textContent.indexOf('#' + selected) >= 0) {
          st = statusFromText((row.querySelector('.cmb-admin-muted') || {}).textContent);
        }
      });
      if (card) paintGroup(card, st);
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
      '<input data-cmb-size-name type="text" maxlength="80" placeholder="이름" class="' + CLS + '">' +
      '<input data-cmb-size-w type="number" min="0" placeholder="W" class="' + CLS + '">' +
      '<span>×</span>' +
      '<input data-cmb-size-d type="number" min="0" placeholder="D" class="' + CLS + '">' +
      '<span>×</span>' +
      '<input data-cmb-size-h type="number" min="0" placeholder="H" class="' + CLS + '">' +
      '<button type="button" data-cmb-size-remove class="px-2.5 py-2 text-sm rounded-lg border">삭제</button>';
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
    var items = parseSizes(ta.value || ta.getAttribute('placeholder'));
    var root = document.createElement('div');
    var list = document.createElement('div');
    items.forEach(function (it) { list.appendChild(makeRow(it, items.length > 1)); });
    var add = document.createElement('button');
    add.type = 'button';
    add.textContent = '추가';
    add.className = 'px-3 py-1.5 text-sm rounded-lg border';
    root.appendChild(list);
    root.appendChild(add);
    ta.parentNode.insertBefore(root, ta);
    function sync() { ta.value = JSON.stringify(collect(list)); ta.dispatchEvent(new Event('input', { bubbles: true })); }
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
