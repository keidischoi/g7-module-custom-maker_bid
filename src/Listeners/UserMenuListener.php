<?php

namespace Modules\Custom\MakerBid\Listeners;

class UserMenuListener
{
    public function handle($payload = null)
    {
        $item = [
            'name' => ['ko' => '의뢰/입찰', 'en' => 'Request / Bid'],
            'slug' => 'maker-bid',
            'url' => '/maker-bid',
            'icon' => 'fa-gavel',
            'order' => 25,
        ];

        if (is_array($payload)) {
            $payload[] = $item;
            return $payload;
        }

        return $item;
    }
}
