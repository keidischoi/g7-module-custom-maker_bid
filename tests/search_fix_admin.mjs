import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';

const src = fs.readFileSync(new URL('../resources/assets/search-fix.js', import.meta.url), 'utf8');

function el(tag, attrs = {}) {
  const node = {
    tagName: String(tag).toUpperCase(),
    nodeType: 1,
    children: [],
    attrs: { ...attrs },
    className: attrs.className || '',
    href: attrs.href || '',
    hidden: !!attrs.hidden,
    parentNode: null,
    innerHTML: '',
    _text: '',
    _display: '',
    value: '',
    classList: {
      add(...c) { node.className = [node.className, ...c].join(' ').trim(); },
      remove(...c) {
        const drop = new Set(c);
        node.className = node.className.split(/\s+/).filter((x) => x && !drop.has(x)).join(' ');
      },
      toggle(c, on) {
        const has = node.className.split(/\s+/).includes(c);
        if (on === false || (on == null && has)) this.remove(c);
        else this.add(c);
      },
    },
    get textContent() { return node._text; },
    set textContent(v) { node._text = String(v ?? ''); },
    getAttribute(name) {
      if (name === 'class') return node.className;
      if (name === 'hidden') return node.hidden ? '' : null;
      return Object.prototype.hasOwnProperty.call(node.attrs, name) ? node.attrs[name] : null;
    },
    setAttribute(name, value) {
      node.attrs[name] = String(value);
      if (name === 'hidden') node.hidden = true;
      if (name === 'href') node.href = String(value);
      if (name === 'class') node.className = String(value);
    },
    removeAttribute(name) {
      delete node.attrs[name];
      if (name === 'hidden') node.hidden = false;
    },
    appendChild(child) {
      child.parentNode = node;
      node.children.push(child);
      return child;
    },
    insertBefore(child, ref) {
      child.parentNode = node;
      const i = node.children.indexOf(ref);
      if (i < 0) node.children.push(child);
      else node.children.splice(i, 0, child);
      return child;
    },
    querySelector(sel) {
      return queryAll(node, sel)[0] || null;
    },
    querySelectorAll(sel) {
      return queryAll(node, sel);
    },
    closest() { return null; },
    addEventListener() {},
    tabIndex: 0,
    style: {
      setProperty(prop, value) {
        if (prop === 'display') node._display = value;
      },
    },
  };
  return node;
}

function matchSel(node, sel) {
  if (sel === '[data-cmb-self-status]') return node.getAttribute('data-cmb-self-status') != null;
  if (sel.startsWith('[data-cmb-self-status="')) {
    const key = sel.slice('[data-cmb-self-status="'.length, -2);
    return node.getAttribute('data-cmb-self-status') === key;
  }
  if (sel.includes('cmb-card-list')) return (node.className || '').includes('cmb-card-list');
  if (sel.includes('cmb-filter-stack') || sel.includes('data-cmb-filter-stack')) {
    return (node.className || '').includes('cmb-filter-stack') || node.getAttribute('data-cmb-filter-stack') != null;
  }
  if (sel.includes('cmb-filter-row-status') || sel.includes('data-cmb-status-chips')) {
    return (node.className || '').includes('cmb-filter-row-status') || node.getAttribute('data-cmb-status-chips') != null;
  }
  if (sel.includes('cmb-filters') || sel.includes('cmb-filter-row-type')) {
    return (node.className || '').includes('cmb-filters') || (node.className || '').includes('cmb-filter-row-type');
  }
  if (sel.includes('data-cmb-free')) return node.getAttribute('data-cmb-free') != null;
  if (sel.includes('data-cmb-sort-free')) return node.getAttribute('data-cmb-sort-free') != null;
  if (sel.includes('data-cmb-native-bar')) return node.getAttribute('data-cmb-native-bar') != null;
  if (sel.includes('data-cmb-native-host')) return node.getAttribute('data-cmb-native-host') != null;
  if (sel.includes('data-cmb-pager')) return node.getAttribute('data-cmb-pager') != null;
  if (sel.includes('cmb-job-card')) return (node.className || '').includes('cmb-job-card');
  if (sel.includes('cmb-search-bar') || sel.includes('data-cmb-search-bar')) {
    return (node.className || '').includes('cmb-search-bar') || node.getAttribute('data-cmb-search-bar') != null;
  }
  return false;
}

function queryAll(root, sel) {
  const parts = String(sel).split(',').map((s) => s.trim()).filter(Boolean);
  const out = [];
  const walk = (n) => {
    if (!n || n.nodeType !== 1) return;
    if (parts.some((p) => matchSel(n, p))) out.push(n);
    (n.children || []).forEach(walk);
  };
  (root.children || []).forEach(walk);
  return out;
}

const page = el('div');
const cardList = el('div', { className: 'cmb-section-card cmb-card-list' });
const stack = el('div', { className: 'cmb-filter-stack', 'data-cmb-filter-stack': '1' });
page.appendChild(stack);
page.appendChild(cardList);

const documentMock = {
  readyState: 'complete',
  documentElement: page,
  body: page,
  activeElement: null,
  addEventListener() {},
  createElement: (tag) => el(tag),
  querySelector(sel) {
    if (String(sel).includes('cmb-card-list')) return cardList;
    return page.querySelector(sel);
  },
  querySelectorAll(sel) {
    return page.querySelectorAll(sel);
  },
};

let fetchedUrl = '';
let fetchedAuth = '';
const jobsPayload = {
  data: [{ id: 9, title: '보류 의뢰', status: 'hold', status_label: '보류', type: 'print_3d', bids_count: 0 }],
  meta: {
    page: 1,
    total: 1,
    last_page: 1,
    per_page: 24,
    viewer: { is_admin: true, user_id: 1 },
    viewer_status_chips: { hold: true, draft: true, disputed: true },
  },
};

const g7 = {
  auth: { getAuthType: () => 'admin', getUser: () => ({ is_super: true }) },
  api: {
    getToken: () => 'admin-token',
    get(url) {
      return Promise.resolve(jobsPayload);
    },
  },
};

const windowMock = {
  G7Core: g7,
  AuthManager: { getInstance: () => ({ getAuthType: () => 'admin' }) },
  addEventListener() {},
  __cmbSearchFix14: false,
};

const ctx = {
  window: windowMock,
  document: documentMock,
  location: { pathname: '/maker-bids', href: 'http://example.test/maker-bids', origin: 'http://example.test' },
  history: { replaceState() {} },
  localStorage: { getItem: () => 'admin-token', setItem() {} },
  G7Core: g7,
  AuthManager: windowMock.AuthManager,
  URL,
  URLSearchParams,
  MutationObserver: class { observe() {} },
  setTimeout,
  setInterval() { return 0; },
  fetch(url, opts) {
    fetchedUrl = String(url);
    fetchedAuth = (opts && opts.headers && opts.headers.Authorization) || '';
    if (String(url).includes('/auth/user')) {
      return Promise.resolve({
        ok: true,
        json: () => Promise.resolve({ data: { uuid: 'admin-1', is_super: true } }),
      });
    }
    return Promise.resolve({ ok: true, json: () => Promise.resolve(jobsPayload) });
  },
};
windowMock.location = ctx.location;
windowMock.document = documentMock;
windowMock.G7Core = g7;
ctx.globalThis = ctx;

vm.createContext(ctx);
vm.runInContext(src, ctx);

await new Promise((r) => setTimeout(r, 500));

const hold = page.querySelectorAll('[data-cmb-self-status]').find((n) => n.getAttribute('data-cmb-self-status') === 'hold');
assert.ok(hold, 'hold chip exists');
assert.equal(hold.hidden, false, 'admin should see hold chip');
assert.equal(hold._display, 'inline-flex', 'hold chip display inline-flex');
assert.match(fetchedUrl, /\/api\/modules\/custom-maker_bids\/jobs/, 'jobs API fetched');
assert.equal(fetchedAuth, 'Bearer admin-token', 'jobs fetch sends bearer token');
assert.match(cardList.innerHTML, /보류 의뢰/, 'hold job card painted');
assert.match(cardList.innerHTML, /cmb-badge-hold/, 'hold badge class painted');

console.log('ok  admin hold chip visible');
console.log('ok  admin hold job card painted');
console.log('ok  jobs fetch sent bearer token');
