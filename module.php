<?php

namespace Modules\Custom\MakerBids;

use App\Extension\AbstractModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Custom\MakerBids\Listeners\LayoutFileFixListener;
use Modules\Custom\MakerBids\Listeners\UserMenuListener;

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
                    $this->adminMenuChild('운영', 'Ops', 'ops', '/admin/maker-bids/ops', 'fa-clipboard-list', 5, 'custom-maker_bids.jobs.read'),
                    $this->adminMenuChild('회원 활동', 'Member activity', 'activity', '/admin/maker-bids/activity', 'fa-user', 6, 'custom-maker_bids.jobs.read'),
                    $this->adminMenuChild('설정', 'Settings', 'settings', '/admin/maker-bids/settings', 'fa-cog', 7, 'custom-maker_bids.settings.read'),
                ],
            ],
        ];
    }

    public function install(): bool
    {
        $this->purgeOrphanAdminMenus();

        return parent::install();
    }

    public function uninstall(): bool
    {
        $this->purgeOrphanAdminMenus();

        return parent::uninstall();
    }

    private function purgeOrphanAdminMenus(): void
    {
        try {
            if (! Schema::hasTable('menus')) {
                return;
            }
            $slugs = [
                'custom-maker_bids',
                'custom-maker_bid',
                'custom-maker_bids-jobs',
                'custom-maker_bids-types',
                'custom-maker_bids-bids',
                'custom-maker_bids-companies',
                'custom-maker_bids-activity',
                'custom-maker_bids-ops',
                'custom-maker_bids-settings',
                'custom-maker_bid-jobs',
                'custom-maker_bid-types',
                'custom-maker_bid-bids',
                'custom-maker_bid-companies',
                'custom-maker_bid-activity',
                'custom-maker_bid-ops',
                'custom-maker_bid-settings',
            ];
            DB::table('menus')
                ->where(function ($q) use ($slugs) {
                    $q->whereIn('slug', $slugs)
                        ->orWhereIn('extension_identifier', ['custom-maker_bids', 'custom-maker_bid']);
                })
                ->delete();
        } catch (\Throwable) {
        }
    }

    public function getSchedules(): array
    {
        return [
            [
                'command' => 'maker-bids:run-schedule',
                'schedule' => 'hourly',
                'description' => 'Close expired maker_bids and notify deadline soon',
            ],
        ];
    }

    public function getHookListeners(): array
    {
        return [UserMenuListener::class, LayoutFileFixListener::class];
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
            'maker_notices',
            'maker_messages',
            'maker_reviews',
            'maker_claims',
            'maker_reports',
            'maker_audits',
            'maker_file_logs',
        ];
    }

    public function getRoutes(): array
    {
        $api = $this->getModulePath().'/src/routes/api.php';
        $web = $this->getModulePath().'/src/routes/web.php';
        $routes = [];
        if (is_file($api)) {
            $routes['api'] = $api;
        }
        if (is_file($web)) {
            $routes['web'] = $web;
        }

        return $routes;
    }

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
