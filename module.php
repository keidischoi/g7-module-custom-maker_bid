<?php

namespace Modules\Custom\MakerBid;

use App\Extension\AbstractModule;

class Module extends AbstractModule
{
    public function getRoles(): array
    {
        return [
            [
                'identifier' => 'custom-maker_bid.maker',
                'name' => ['ko' => '제작자', 'en' => 'Maker'],
                'description' => ['ko' => '의뢰에 입찰할 수 있습니다', 'en' => 'Can bid on jobs'],
            ],
        ];
    }

    public function getPermissions(): array
    {
        return [
            'name' => ['ko' => '제작 의뢰', 'en' => 'Maker Bid'],
            'description' => ['ko' => '제작 의뢰 권한', 'en' => 'Maker bid permissions'],
            'categories' => [
                [
                    'identifier' => 'jobs',
                    'name' => ['ko' => '의뢰', 'en' => 'Jobs'],
                    'description' => ['ko' => '의뢰 관리', 'en' => 'Manage jobs'],
                    'permissions' => [
                        ['action' => 'read', 'name' => ['ko' => '조회', 'en' => 'Read'], 'description' => ['ko' => '조회', 'en' => 'Read'], 'type' => 'admin', 'roles' => ['admin']],
                        ['action' => 'create', 'name' => ['ko' => '생성', 'en' => 'Create'], 'description' => ['ko' => '생성', 'en' => 'Create'], 'type' => 'admin', 'roles' => ['admin']],
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
                'name' => ['ko' => '제작 의뢰', 'en' => 'Maker Bid'],
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
        return [];
    }
}
