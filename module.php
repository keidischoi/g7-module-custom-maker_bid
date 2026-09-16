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
                $this->permissionCategory('settings', '설정', 'Settings', '모듈 설정', 'Module settings', [
                    ['read', '조회', 'Read'],
                    ['update', '수정', 'Update'],
                ]),
            ],
        ];
    }

    public function getAdminMenus(): array
    {
        return [
            [
                'name' => ['ko' => '의뢰/입찰', 'en' => 'Maker Bid'],
                'slug' => 'custom-maker_bids',
                'url' => '/admin/maker-bids',
                'icon' => 'fa-gavel',
                'order' => 42,
                'children' => [
                    $this->adminMenuChild('의뢰 목록', 'Jobs', 'jobs', '/admin/maker-bids', 'fa-list', 1, 'custom-maker_bids.jobs.read'),
                    $this->adminMenuChild('유형 관리', 'Job types', 'types', '/admin/maker-bids/types', 'fa-tags', 2, 'custom-maker_bids.jobs.read'),
                    $this->adminMenuChild('입찰 관리', 'Bids', 'bids', '/admin/maker-bids/bids', 'fa-gavel', 3, 'custom-maker_bids.bids.read'),
                    $this->adminMenuChild('회사 목록', 'Companies', 'companies', '/admin/maker-bids/companies', 'fa-building', 4, 'custom-maker_bids.companies.read'),
                    $this->adminMenuChild('회원 활동', 'Member activity', 'activity', '/admin/maker-bids/activity', 'fa-user', 5, 'custom-maker_bids.jobs.read'),
                    $this->adminMenuChild('설정', 'Settings', 'settings', '/admin/maker-bids/settings', 'fa-cog', 6, 'custom-maker_bids.settings.read'),
                ],
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
            'maker_job_types',
            'maker_job_files',
            'maker_module_settings',
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
     * @return array<string, mixed>
     */
    private function adminMenuChild(
        string $nameKo,
        string $nameEn,
        string $slugSuffix,
        string $url,
        string $icon,
        int $order,
        string $permission,
    ): array {
        return [
            'name' => ['ko' => $nameKo, 'en' => $nameEn],
            'slug' => 'custom-maker_bids-'.$slugSuffix,
            'url' => $url,
            'icon' => $icon,
            'order' => $order,
            'permission' => $permission,
        ];
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
