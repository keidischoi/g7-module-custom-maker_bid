<?php

namespace Modules\Custom\MakerBid\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class AssetController extends Controller
{
    public function nav(): Response
    {
        $path = dirname(__DIR__, 3).'/resources/assets/nav.js';
        $js = is_file($path) ? (string) file_get_contents($path) : '';

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-cache',
        ]);
    }
}
