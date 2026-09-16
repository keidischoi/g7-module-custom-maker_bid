(function () {
  var DAUM_SRC = 'https://t1.daumcdn.net/mapjsapi/bundle/postcode/prod/postcode.v2.js';
  var TYPES_URL = '/api/modules/custom-maker_bid/job-types';
  var typesCache = null;
  var daumLoading = false;

  function dispatch(handler, params) {
    if (window.G7Core && typeof window.G7Core.dispatch === 'function') {
      window.G7Core.dispatch({ handler: handler, params: params || {} });
    }
  }

  function setLocal(map) {
    var params = { target: 'local' };
    var key;
    for (key in map) {
      if (Object.prototype.hasOwnProperty.call(map, key)) {
        params[key] = map[key];
      }
    }
    dispatch('setState', params);
  }

  function fillNamed(name, value) {
    var nodes = document.querySelectorAll('[name="' + name + '"]');
    var i;
    for (i = 0; i < nodes.length; i++) {
      nodes[i].value = value;
      try {
        nodes[i].dispatchEvent(new Event('input', { bubbles: true }));
        nodes[i].dispatchEvent(new Event('change', { bubbles: true }));
      } catch (e) {}
    }
  }

  function loadDaum(cb) {
    if (window.daum && window.daum.Postcode) {
      cb();
      return;
    }
    if (daumLoading) {
      setTimeout(function () {
        loadDaum(cb);
      }, 200);
      return;
    }
    daumLoading = true;
    var s = document.createElement('script');
    s.src = DAUM_SRC;
    s.async = true;
    s.onload = function () {
      daumLoading = false;
      cb();
    };
    s.onerror = function () {
      daumLoading = false;
      dispatch('toast', { type: 'error', message: '주소 검색을 불러오지 못했습니다.' });
    };
    document.head.appendChild(s);
  }

  function openPostcode() {
    loadDaum(function () {
      new window.daum.Postcode({
        oncomplete: function (data) {
          var zip = data.zonecode || '';
          var addr = data.roadAddress || data.jibunAddress || data.address || '';
          fillNamed('zipcode', zip);
          fillNamed('address', addr);
          setLocal({
            'form.zipcode': zip,
            'form.address': addr
          });
        }
      }).open();
    });
  }

  function applyTypeFlags(slug) {
    if (!slug || !typesCache) {
      return;
    }
    var i;
    var row = null;
    for (i = 0; i < typesCache.length; i++) {
      if (typesCache[i].value === slug || typesCache[i].slug === slug) {
        row = typesCache[i];
        break;
      }
    }
    if (!row) {
      return;
    }
    var needs = row.requires_address !== false && row.is_design_only !== true;
    setLocal({
      'form.requires_address': needs ? '1' : '0'
    });
  }

  function loadTypes(cb) {
    if (typesCache) {
      cb();
      return;
    }
    fetch(TYPES_URL, { headers: { Accept: 'application/json' } })
      .then(function (res) {
        return res.json();
      })
      .then(function (body) {
        typesCache = (body && body.data) || [];
        cb();
      })
      .catch(function () {
        typesCache = [];
        cb();
      });
  }

  function bind() {
    var btn = document.querySelector('[data-cmb-postcode]');
    if (btn && !btn.getAttribute('data-cmb-bound')) {
      btn.setAttribute('data-cmb-bound', '1');
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        openPostcode();
      });
    }
    var typeSelect = document.querySelector('[name="type"]');
    if (typeSelect && !typeSelect.getAttribute('data-cmb-bound')) {
      typeSelect.setAttribute('data-cmb-bound', '1');
      typeSelect.addEventListener('change', function () {
        loadTypes(function () {
          applyTypeFlags(typeSelect.value);
        });
      });
      if (typeSelect.value) {
        loadTypes(function () {
          applyTypeFlags(typeSelect.value);
        });
      }
    }
  }

  bind();
  document.addEventListener('DOMContentLoaded', bind);
  setTimeout(bind, 300);
  setTimeout(bind, 1000);
  setTimeout(bind, 2500);
})();
