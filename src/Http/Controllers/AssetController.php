<?php

namespace Modules\Custom\MakerBid\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class AssetController extends Controller
{
    public function nav(): Response
    {
        return $this->js('nav.js');
    }

    public function form(): Response
    {
        return $this->js('form.js');
    }

    private function js(string $name): Response
    {
        $path = dirname(__DIR__, 3).'/resources/assets/'.$name;
        $js = is_file($path) ? (string) file_get_contents($path) : '';

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
