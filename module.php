<?php

namespace Modules\Custom\MakerBid;

use App\Extension\AbstractModule;
use Modules\Custom\MakerBid\Listeners\UserMenuListener;

class Module extends AbstractModule
{
    public function getRoles(): array
    {
        return [];
    }

    public function getPermissions(): array
    {
        return [
            'name' => ['ko' => '의뢰/입찰', 'en' => 'Maker Bid'],
            'description' => ['ko' => '의뢰/입찰 관리', 'en' => 'Manage jobs'],
            'categories' => [
                [
                    'identifier' => 'jobs',
                    'name' => ['ko' => '의뢰', 'en' => 'Jobs'],
                    'description' => ['ko' => '의뢰 관리', 'en' => 'Jobs'],
                    'permissions' => [
                        ['action' => 'read', 'name' => ['ko' => '조회', 'en' => 'Read'], 'description' => ['ko' => '조회', 'en' => 'Read'], 'type' => 'admin', 'roles' => ['admin']],
                        ['action' => 'update', 'name' => ['ko' => '수정', 'en' => 'Update'], 'description' => ['ko' => '수정', 'en' => 'Update'], 'type' => 'admin', 'roles' => ['admin']],
                        ['action' => 'delete', 'name' => ['ko' => '삭제', 'en' => 'Delete'], 'description' => ['ko' => '삭제', 'en' => 'Delete'], 'type' => 'admin', 'roles' => ['admin']],
                    ],
                ],
            ],
        ];
    }

    public function getAdminMenus(): array
    {
        return [
            [
                'name' => ['ko' => '의뢰/입찰', 'en' => 'Jobs'],
                'slug' => 'custom-maker_bid',
                'url' => '/admin/maker-bid',
                'icon' => 'fa-gavel',
                'order' => 42,
                'permission' => 'custom-maker_bid.jobs.read',
            ],
        ];
    }

    public function getHookListeners(): array
    {
        return [UserMenuListener::class];
    }
}
