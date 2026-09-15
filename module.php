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
            'description' => ['ko' => '의뢰/입찰 관리', 'en' => 'Manage jobs, bids, and companies'],
            'categories' => [
                $this->permissionCategory('jobs', '의뢰', 'Jobs', '의뢰 관리', 'Manage jobs', [
                    ['read', '조회', 'Read'],
                    ['update', '수정', 'Update'],
                    ['delete', '삭제', 'Delete'],
                ]),
                $this->permissionCategory('bids', '입찰', 'Bids', '입찰 관리', 'Manage bids', [
                    ['read', '조회', 'Read'],
                    ['update', '수정', 'Update'],
                    ['delete', '삭제', 'Delete'],
                ]),
                $this->permissionCategory('companies', '업체', 'Companies', '입찰 업체 관리', 'Manage maker companies', [
                    ['read', '조회', 'Read'],
                    ['create', '등록', 'Create'],
                    ['update', '승인/거절', 'Approve / reject'],
                    ['delete', '삭제', 'Delete'],
                ], ['admin']),
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

    public function getDynamicTables(): array
    {
        return [
            'maker_jobs',
            'maker_bids',
            'maker_companies',
        ];
    }

    public function getRoutes(): array
    {
        $api = $this->getModulePath().'/src/routes/api.php';
        $routes = [];
        if (is_file($api)) {
            $routes['api'] = $api;
        }

        return $routes;
    }

    /**
     * @param  list<array{0:string,1:string,2:string}>  $actions
     * @param  list<string>  $deleteRoles
     * @return array<string, mixed>
     */
    private function permissionCategory(
        string $identifier,
        string $nameKo,
        string $nameEn,
        string $descKo,
        string $descEn,
        array $actions,
        array $deleteRoles = ['admin'],
    ): array {
        $permissions = [];
        foreach ($actions as [$action, $ko, $en]) {
            $roles = $action === 'delete' ? $deleteRoles : ['admin', 'manager'];
            $permissions[] = [
                'action' => $action,
                'name' => ['ko' => $ko, 'en' => $en],
                'description' => ['ko' => $ko, 'en' => $en],
                'type' => 'admin',
                'roles' => $roles,
            ];
        }

        return [
            'identifier' => $identifier,
            'name' => ['ko' => $nameKo, 'en' => $nameEn],
            'description' => ['ko' => $descKo, 'en' => $descEn],
            'permissions' => $permissions,
        ];
    }
}
