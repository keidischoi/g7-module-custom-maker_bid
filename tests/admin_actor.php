<?php

declare(strict_types=1);

require __DIR__.'/bootstrap.php';

if (! class_exists(\Illuminate\Http\Request::class)) {
    eval('namespace Illuminate\Http { class Request {} }');
}
if (! class_exists(\Illuminate\Support\Facades\Auth::class)) {
    eval('namespace Illuminate\Support\Facades { class Auth { public static function guard($g = null) { return new class { public function user() { return null; } }; } public static function user() { return null; } } }');
}

$svc = (new ReflectionClass(\Modules\Custom\MakerBids\Services\JobService::class))->newInstanceWithoutConstructor();

expectFalse('guest is not admin', $svc->isAdminActor(null));
expectFalse('plain member is not admin', $svc->isAdminActor(new class {
    public int $id = 5;
}));
expectTrue('is_super column is admin', $svc->isAdminActor(new class {
    public int $id = 1;
    public bool $is_super = true;
}));
expectTrue('hasRole admin is admin', $svc->isAdminActor(new class {
    public int $id = 2;
    public function hasRole(string $role): bool
    {
        return $role === 'admin';
    }
}));
expectTrue('isAdmin() is admin', $svc->isAdminActor(new class {
    public int $id = 3;
    public function isAdmin(): bool
    {
        return true;
    }
}));
expectTrue('isSuperAdmin() is admin', $svc->isAdminActor(new class {
    public int $id = 4;
    public function isSuperAdmin(): bool
    {
        return true;
    }
}));
expectTrue('is_super still admin if isAdmin throws', $svc->isAdminActor(new class {
    public int $id = 6;
    public bool $is_super = true;
    public function isAdmin(): bool
    {
        throw new RuntimeException('roles query failed');
    }
}));
expectTrue('jobs.read permission is admin', $svc->isAdminActor(new class {
    public int $id = 7;
    public function hasPermission(string $perm): bool
    {
        return $perm === 'custom-maker_bids.jobs.read';
    }
}));
expectFalse('unrelated permission is not admin', $svc->isAdminActor(new class {
    public int $id = 8;
    public function hasPermission(string $perm): bool
    {
        return $perm === 'core.users.read';
    }
}));

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
