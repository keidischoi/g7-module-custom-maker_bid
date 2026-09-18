<?php

namespace Modules\Custom\MakerBids\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class AssetController extends Controller
{
    public function nav(): Response
    {
        return $this->asset('nav.js', 'application/javascript; charset=UTF-8');
    }

    public function form(): Response
    {
        return $this->asset('form.js', 'application/javascript; charset=UTF-8');
    }

    public function existingFiles(): Response
    {
        return $this->asset('existing-files.js', 'application/javascript; charset=UTF-8');
    }

    public function bidSubmit(): Response
    {
        return $this->asset('bid-submit.js', 'application/javascript; charset=UTF-8');
    }

    public function page(): Response
    {
        return $this->asset('page.js', 'application/javascript; charset=UTF-8', static function (string $body): string {
            $body = str_replace('.cmb-bid-submit, [data-cmb-bid-submit]', '.cmb-bid-submit-off, [data-cmb-bid-submit-off]', $body);
            $body = str_replace('.cmb-bid-update, [data-cmb-bid-update]', '.cmb-bid-update-off, [data-cmb-bid-update-off]', $body);
            $body = str_replace('.cmb-open-bid-toggle', '.cmb-open-bid-toggle-off', $body);
            $body = str_replace('cmb-open-bid-form', 'cmb-open-bid-form-off', $body);
            $body = str_replace("if (r.status === 401) msg = '로그인이 필요합니다.", "if (false) msg = '로그인이 필요합니다.", $body);
            $redirect = <<<'JS'
(function () {
  document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target.closest('button, a, [role="button"]') : null;
    if (!t) return;
    var label = (t.textContent || '').replace(/\s+/g, ' ').trim();
    if (label.indexOf('견적 넣기') === -1 && label.indexOf('견적서 작성') === -1) return;
    e.preventDefault();
    e.stopPropagation();
    if (e.stopImmediatePropagation) e.stopImmediatePropagation();
    var root = t.closest('[data-job-id], .cmb-job-card, .cmb-list-item, article, li, section') || document;
    var id = t.getAttribute('data-job-id') || (root && root.getAttribute && root.getAttribute('data-job-id')) || '';
    if (!id) {
      var a = root.querySelector && root.querySelector('a[href*="/maker-bids/"]');
      var m = a && (a.getAttribute('href') || '').match(/\/maker-bids\/(\d+)/);
      if (m) id = m[1];
    }
    if (!id) return;
    location.href = '/maker-bids/bids?job=' + encodeURIComponent(id);
  }, true);
})();
JS;
            return $redirect.$body;
        });
    }

    public function formCss(): Response
    {
        return $this->asset('form.css', 'text/css; charset=UTF-8');
    }

    public function adminCss(): Response
    {
        return $this->asset('admin.css', 'text/css; charset=UTF-8');
    }

    public function adminJs(): Response
    {
        return $this->asset('admin.js', 'application/javascript; charset=UTF-8');
    }

    private function asset(string $name, string $contentType, ?callable $mutate = null): Response
    {
        $path = dirname(__DIR__, 3).'/resources/assets/'.$name;
        $body = is_file($path) ? (string) file_get_contents($path) : '';
        if ($mutate !== null) {
            $body = $mutate($body);
        }
        return response($body, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-cache',
        ]);
    }
}
