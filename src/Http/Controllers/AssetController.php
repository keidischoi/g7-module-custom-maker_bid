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
        return $this->asset('page.js', 'application/javascript; charset=UTF-8');
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

    private function asset(string $name, string $contentType): Response
    {
        $path = dirname(__DIR__, 3).'/resources/assets/'.$name;
        $body = is_file($path) ? (string) file_get_contents($path) : '';

        return response($body, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'no-cache',
        ]);
    }
}
