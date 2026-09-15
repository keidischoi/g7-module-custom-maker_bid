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
                'name' => ['ko' => '제작자/업체', 'en' => 'Maker'],
                'description' => ['ko' => '견적을 냄 수 있습니다', 'en' => 'Can bid'],
            ],
        ];
    }

    public function getPermissions(): array
    {
        return [
            'name' => ['ko' => '제작 의뢰', 'en' => 'Maker Bid'],
            'description' => ['ko' => '의뢰/입찰 관리', 'en' => 'Manage jobs and bids'],
            'categories' => [
                [
                    'identifier' => 'jobs',
                    'name' => ['ko' => '의뢰', 'en' => 'Jobs'],
                    'description' => ['ko' => '의뢰 관리', 'en' => 'Jobs'],
                    'permissions' => [
                        ['action' => 'read', 'name' => ['ko' => '조회', 'en' => 'Read'], 'description' => ['ko' => '조회', 'en' => 'Read'], 'type' => 'admin', 'roles' => ['admin']],
                        ['action' => 'update', 'name' => ['ko' => '수정/보류', 'en' => 'Update'], 'description' => ['ko' => '수정 보류', 'en' => 'Update hold'], 'type' => 'admin', 'roles' => ['admin']],
                        ['action' => 'delete', 'name' => ['ko' => '삭제', 'en' => 'Delete'], 'description' => ['ko' => '삭제', 'en' => 'Delete'], 'type' => 'admin', 'roles' => ['admin']],
                    ],
                ],
                [
                    'identifier' => 'companies',
                    'name' => ['ko' => '입찰 업체', 'en' => 'Companies'],
                    'description' => ['ko' => '업체 관리', 'en' => 'Companies'],
                    'permissions' => [
                        ['action' => 'read', 'name' => ['ko' => '조회', 'en' => 'Read'], 'description' => ['ko' => '조회', 'en' => 'Read'], 'type' => 'admin', 'roles' => ['admin']],
                        ['action' => 'update', 'name' => ['ko' => '승인', 'en' => 'Approve'], 'description' => ['ko' => '승인', 'en' => 'Approve'], 'type' => 'admin', 'roles' => ['admin']],
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

    public function getUserMenus(): array
    {
        return [
            [
                'name' => ['ko' => '의뢰/입찰', 'en' => 'Request / Bid'],
                'slug' => 'maker-bid',
                'url' => '/maker-bid',
                'icon' => 'fa-gavel',
                'order' => 25,
            ],
        ];
    }

    public function getHookListeners(): array
    {
        return [];
    }
}
