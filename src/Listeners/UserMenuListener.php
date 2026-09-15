<?php

namespace Modules\Custom\MakerBid\Listeners;

use App\Contracts\HookListenerInterface;

class UserMenuListener implements HookListenerInterface
{
    public static function getSubscribedHooks(): array
    {
        return [
            'core.menu.filter_list' => [
                'method' => 'filterMenus',
                'type' => 'filter',
                'priority' => 20,
                'sync' => true,
            ],
            'core.menu.filter_user_menus' => [
                'method' => 'filterMenus',
                'type' => 'filter',
                'priority' => 20,
                'sync' => true,
            ],
            'core.menu.after_list' => [
                'method' => 'filterMenus',
                'type' => 'filter',
                'priority' => 20,
                'sync' => true,
            ],
        ];
    }

    public function filterMenus($menus = [])
    {
        $item = [
            'name' => ['ko' => '의뢰/입찰', 'en' => 'Request / Bid'],
            'slug' => 'maker-bid',
            'url' => '/maker-bid',
            'icon' => 'fa-gavel',
            'order' => 25,
            'is_active' => true,
        ];

        if (! is_array($menus)) {
            return $menus;
        }

        $menus[] = $item;

        return $menus;
    }
}
