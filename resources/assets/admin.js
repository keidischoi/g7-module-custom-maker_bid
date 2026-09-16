(function () {
  function adminRoot() { return document.querySelector('.cmb-admin'); }

  function parseSizes(raw) {
    if (!raw) return [{ name: '', w: '', d: '', h: '' }];
    if (Array.isArray(raw)) {
      return raw.map(function (r) {
        return {
          name: r && r.name != null ? String(r.name) : '',
          w: r && r.w != null ? r.w : '',
          d: r && r.d != null ? r.d : '',
          h: r && r.h != null ? r.h : ''
        };
      });
    }
    try { return parseSizes(JSON.parse(String(raw))); } catch (e) { return [{ name: '', w: '', d: '', h: '' }]; }
  }

  function rowsJson(root) {
    var rows = [];
    root.querySelectorAll('[data-cmb-admin-size-row]').forEach(function (row) {
      rows.push({
        name: row.querySelector('[data-k="name"]').value,
        w: row.querySelector('[data-k="w"]').value === '' ? null : Number(row.querySelector('[data-k="w"]').value),
        d: row.querySelector('[data-k="d"]').value === '' ? null : Number(row.querySelector('[data-k="d"]').value),
        h: row.querySelector('[data-k="h"]').value === '' ? null : Number(row.querySelector('[data-k="h"]').value)
      });
    });
    return JSON.stringify(rows);
  }

  function makeRow(item) {
    var row = document.createElement('div');
    row.setAttribute('data-cmb-admin-size-row', '1');
    row.style.cssText = 'display:flex;flex-wrap:wrap;gap:6px;align-items:center;margin-bottom:6px;';
    row.innerHTML = '<input data-k="name" type="text" placeholder="이름" class="rounded-lg border px-2 py-1.5 text-sm" />'
      + '<input data-k="w" type="number" placeholder="W" class="rounded-lg border px-2 py-1.5 text-sm" style="width:4.5rem" />'
      + '<span>×</span>'
      + '<input data-k="d" type="number" placeholder="D" class="rounded-lg border px-2 py-1.5 text-sm" style="width:4.5rem" />'
      + '<span>×</span>'
      + '<input data-k="h" type="number" placeholder="H" class="rounded-lg border px-2 py-1.5 text-sm" style="width:4.5rem" />'
      + '<button type="button" data-cmb-admin-size-del class="px-2 py-1 text-sm rounded-lg border">삭제</button>';
    row.querySelector('[data-k="name"]').value = item.name || '';
    row.querySelector('[data-k="w"]').value = item.w == null ? '' : item.w;
    row.querySelector('[data-k="d"]').value = item.d == null ? '' : item.d;
    row.querySelector('[data-k="h"]').value = item.h == null ? '' : item.h;
    return row;
  }

  function enhanceSizes() {
    var ta = document.querySelector('textarea[name="sizes_json"]');
    if (!ta || ta.getAttribute('data-cmb-size-ui')) return;
    ta.setAttribute('data-cmb-size-ui', '1');
    ta.style.display = 'none';
    var box = document.createElement('div');
    box.id = 'cmb-admin-sizes';
    parseSizes(ta.value || ta.getAttribute('placeholder')).forEach(function (it) { box.appendChild(makeRow(it)); });
    var add = document.createElement('button');
    add.type = 'button';
    add.textContent = '추가';
    add.className = 'px-3 py-1 text-sm rounded-lg border';
    add.addEventListener('click', function (e) {
      e.preventDefault();
      box.insertBefore(makeRow({ name: '', w: '', d: '', h: '' }), add);
    });
    box.appendChild(add);
    ta.parentNode.insertBefore(box, ta);
    var sync = function () {
      ta.value = rowsJson(box);
      ta.dispatchEvent(new Event('input', { bubbles: true }));
      ta.dispatchEvent(new Event('change', { bubbles: true }));
    };
    box.addEventListener('input', sync);
    box.addEventListener('click', function (e) {
      var btn = e.target.closest && e.target.closest('[data-cmb-admin-size-del]');
      if (!btn) return;
      e.preventDefault();
      if (box.querySelectorAll('[data-cmb-admin-size-row]').length <= 1) return;
      btn.closest('[data-cmb-admin-size-row]').remove();
      sync();
    });
    sync();
  }

  function start() {
    if (!adminRoot()) return;
    enhanceSizes();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
  else start();
  setTimeout(enhanceSizes, 400);
  setTimeout(enhanceSizes, 1200);
})();
