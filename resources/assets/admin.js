(function () {
  /* G7 options-mode Select is a custom dropdown, not a native <select>.
     className often lands on a wrapper/display:contents node, and the open
     menu is portaled outside .cmb-admin. 0.9.4 CSS (.cmb-admin-select,
     body has() + radix trigger width) did not match live G7, so size
     the real trigger/listbox with classes we control + inline styles. */
  var HOST_SEL = '.cmb-admin-select-host';
  var TRIGGER_SEL =
    'button, [role="combobox"], [aria-haspopup="listbox"], [aria-haspopup="true"], [data-slot="select-trigger"], [data-slot="trigger"], select';
  var LIST_SEL =
    '[role="listbox"], [data-slot="select-content"], [data-radix-select-content], [data-headlessui-state="open"], [id^="headlessui-listbox-options"]';

  function adminRoot() {
    return document.querySelector('.cmb-admin');
  }

  function isOptionNode(el) {
    if (!el || !el.closest) {
      return false;
    }
    return !!el.closest('[role="listbox"], [data-slot="select-content"], [data-radix-select-content]');
  }

  function triggerOf(host) {
    var list = host.querySelectorAll(TRIGGER_SEL);
    var i;
    var el;
    for (i = 0; i < list.length; i++) {
      el = list[i];
      if (isOptionNode(el)) {
        continue;
      }
      return el;
    }
    if (host.matches && host.matches(TRIGGER_SEL)) {
      return host;
    }
    return null;
  }

  function styleTrigger(el, host) {
    if (!el || (el.tagName || '').toUpperCase() === 'INPUT') {
      return;
    }
    el.classList.add('cmb-admin-select-trigger');
    el.style.setProperty('box-sizing', 'border-box', 'important');
    el.style.setProperty('white-space', 'nowrap', 'important');
    el.style.setProperty('width', '100%', 'important');
    el.style.setProperty('max-width', '100%', 'important');
    el.style.setProperty('display', 'flex', 'important');
    el.style.setProperty('align-items', 'center', 'important');
    el.style.setProperty('justify-content', 'space-between', 'important');
    if (host && !host.classList.contains('cmb-admin-filter-field')) {
      el.style.setProperty('padding-left', '0.75rem', 'important');
      el.style.setProperty('padding-right', '2.25rem', 'important');
      el.style.setProperty('min-height', '2.25rem', 'important');
    }
  }

  function styleHosts() {
    document.querySelectorAll(HOST_SEL).forEach(function (host) {
      var t = triggerOf(host);
      if (t) {
        styleTrigger(t, host);
      }
    });
    document.querySelectorAll('.cmb-admin .cmb-admin-select').forEach(function (el) {
      var host = el.closest(HOST_SEL) || el.parentElement || el;
      if (el.matches && el.matches(TRIGGER_SEL) && !isOptionNode(el)) {
        styleTrigger(el, host);
        return;
      }
      var t = triggerOf(el);
      if (t) {
        styleTrigger(t, host);
      }
    });
  }

  function openTrigger() {
    var root = adminRoot();
    if (!root) {
      return null;
    }
    return (
      root.querySelector('[aria-expanded="true"]') ||
      root.querySelector('[data-state="open"]') ||
      root.querySelector('button[aria-expanded="true"]')
    );
  }

  function optionNodes(box) {
    return box.querySelectorAll(
      '[role="option"], [data-slot="select-item"], [data-value], li'
    );
  }

  function styleListbox(box) {
    var trigger = openTrigger();
    if (!trigger || !trigger.closest || !trigger.closest('.cmb-admin')) {
      return;
    }
    box.classList.add('cmb-admin-listbox');
    var min = Math.max(trigger.getBoundingClientRect().width || 0, 14 * 16);
    var opts = optionNodes(box);
    var i;
    var opt;
    var w;
    for (i = 0; i < opts.length; i++) {
      opt = opts[i];
      opt.style.setProperty('white-space', 'nowrap', 'important');
      opt.style.setProperty('word-break', 'keep-all', 'important');
      opt.style.setProperty('overflow-wrap', 'normal', 'important');
      opt.style.setProperty('width', 'auto', 'important');
      opt.style.setProperty('min-width', '100%', 'important');
      w = (opt.scrollWidth || opt.getBoundingClientRect().width || 0) + 48;
      if (w > min) {
        min = w;
      }
    }
    var px = Math.ceil(min) + 'px';
    box.style.setProperty('white-space', 'nowrap', 'important');
    box.style.setProperty('word-break', 'keep-all', 'important');
    box.style.setProperty('width', 'max-content', 'important');
    box.style.setProperty('min-width', px, 'important');
    box.style.setProperty('max-width', 'min(90vw, 40rem)', 'important');
    box.style.setProperty('box-sizing', 'border-box', 'important');

    var wrap = box.parentElement;
    var hops = 0;
    while (wrap && wrap !== document.body && hops < 6) {
      hops += 1;
      if (
        wrap.hasAttribute('data-radix-popper-content-wrapper') ||
        wrap.hasAttribute('data-floating-ui-portal') ||
        wrap.hasAttribute('data-radix-portal') ||
        (wrap.style && (wrap.style.position === 'absolute' || wrap.style.position === 'fixed'))
      ) {
        wrap.classList.add('cmb-admin-listbox');
        wrap.style.setProperty('min-width', px, 'important');
        wrap.style.setProperty('width', 'max-content', 'important');
      }
      wrap = wrap.parentElement;
    }
  }

  function scanListboxes() {
    if (!openTrigger()) {
      return;
    }
    document.querySelectorAll(LIST_SEL).forEach(styleListbox);
  }

  function onEvent() {
    styleHosts();
    window.setTimeout(scanListboxes, 0);
    window.setTimeout(scanListboxes, 40);
    window.setTimeout(scanListboxes, 160);
  }

  function injectPortalCss() {
    if (document.getElementById('cmb-admin-select-portal-css')) {
      return;
    }
    var s = document.createElement('style');
    s.id = 'cmb-admin-select-portal-css';
    s.textContent =
      'html.cmb-admin-ui [role="listbox"],html.cmb-admin-ui [data-slot="select-content"],html.cmb-admin-ui [data-radix-select-content],html.cmb-admin-ui [data-radix-popper-content-wrapper]{width:max-content!important;min-width:14rem!important;max-width:min(90vw,40rem)!important;white-space:nowrap!important;word-break:keep-all!important;overflow-wrap:normal!important;box-sizing:border-box!important;}' +
      'html.cmb-admin-ui [role="option"],html.cmb-admin-ui [data-slot="select-item"]{white-space:nowrap!important;word-break:keep-all!important;overflow-wrap:normal!important;width:auto!important;min-width:100%!important;display:flex!important;flex-direction:row!important;align-items:center!important;}';
    (document.head || document.documentElement).appendChild(s);
  }

  function start() {
    if (!adminRoot()) {
      return;
    }
    document.documentElement.classList.add('cmb-admin-ui');
    injectPortalCss();
    styleHosts();
    scanListboxes();
    window.setTimeout(scanListboxes, 0);
    window.setTimeout(scanListboxes, 50);
    if (window.MutationObserver) {
      var obs = new MutationObserver(function () {
        styleHosts();
        scanListboxes();
      });
      obs.observe(document.documentElement, {
        childList: true,
        subtree: true,
        attributes: true,
        attributeFilter: ['aria-expanded', 'data-state', 'class']
      });
    }
    document.addEventListener('click', onEvent, true);
    document.addEventListener('pointerdown', onEvent, true);
    document.addEventListener('keydown', onEvent, true);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
  } else {
    start();
  }
})();
