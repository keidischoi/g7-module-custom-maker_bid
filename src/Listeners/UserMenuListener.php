<?php

namespace Modules\Custom\MakerBid\Listeners;

use App\Contracts\Extension\HookListenerInterface;

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
        ];
    }

    public function handle(...$args): void
    {
    }

    public function filterMenus($menus = [])
    {
        if (! is_array($menus)) {
            return $menus;
        }

        $menus[] = [
            'name' => ['ko' => '의뢰/입찰', 'en' => 'Request / Bid'],
            'slug' => 'maker-bid',
            'url' => '/maker-bid',
            'icon' => 'fa-gavel',
            'order' => 25,
            'is_active' => true,
        ];

        return $menus;
    }
}
