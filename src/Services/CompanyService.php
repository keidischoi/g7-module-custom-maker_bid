<?php

namespace Modules\Custom\MakerBids\Services;

use Illuminate\Http\Request;
use Modules\Custom\MakerBids\Models\MakerBid;
use Modules\Custom\MakerBids\Models\MakerCompany;
use Modules\Custom\MakerBids\Models\MakerJobFile;
use Modules\Custom\MakerBids\Support\CompanyPresenter;
use Modules\Custom\MakerBids\Support\CompanyRules;
use Modules\Custom\MakerBids\Support\DomainException;
use Modules\Custom\MakerBids\Support\UploadRules;

class CompanyService
{
    public function __construct(
        private readonly JobFileService $files,
        private readonly JobTypeService $types,
    ) {}

    public function formDefaults(object $user): array
    {
        $token = $this->files->newUploadToken();
        $name = (string) ($user->name ?? '');
        $phone = (string) ($user->mobile ?? $user->phone ?? '');
        $email = (string) ($user->email ?? '');

        return [
            'upload_token' => $token,
            'name' => $name,
            'profile_name' => $name,
            'manager_name' => $name,
            'phone' => $phone,
            'email' => $email,
            'zipcode' => (string) ($user->zipcode ?? ''),
            'address' => (string) ($user->address ?? ''),
            'address_detail' => (string) ($user->address_detail ?? ''),
            'types' => $this->types->listPublic()->map->toOptionArray()->values()->all(),
        ];
    }

    public function apply(int $userId, array $payload): array
    {
        $existing = MakerCompany::query()->where('user_id', $userId)->first();

        if ($existing) {
            // Partial update: blank/missing keys keep DB values (logo-only save safe).
            $attrs = CompanyRules::applicantUpdateAttributes($payload, $existing->toArray());
            if ($existing->status === 'approved') {
                $attrs['status'] = 'approved';
            } else {
                $attrs['status'] = 'pending';
                $attrs['rejected_reason'] = null;
                $attrs['hold_reason'] = null;
                $attrs['reviewed_at'] = null;
            }
            $this->assertBusinessNo($attrs, (int) $existing->id);
            $existing->fill($attrs);
            $existing->save();
            $row = $existing->fresh() ?? $existing;
        } else {
            $attrs = CompanyRules::applicantAttributes($payload);
            $attrs['user_id'] = $userId;
            $attrs['status'] = 'pending';
            $attrs['rejected_reason'] = null;
            $attrs['hold_reason'] = null;
            $attrs['reviewed_at'] = null;
            $this->assertBusinessNo($attrs);
            $row = MakerCompany::query()->create($attrs);
        }

        $this->attachLogo($row, $userId, (string) ($payload['upload_token'] ?? ''));

        return CompanyPresenter::present($row->fresh() ?? $row, 'owner');
    }

    public function mine(int $userId): ?array
    {
        $row = MakerCompany::query()->where('user_id', $userId)->first();
        if ($row === null) {
            return null;
        }
        $this->ensureUploadToken($row);

        return CompanyPresenter::present($row->fresh() ?? $row, 'owner');
    }

    public function listPublic(): array
    {
        return $this->listingQuery()
            ->where('status', 'approved')
            ->limit(200)
            ->get()
            ->map(fn (MakerCompany $row): array => CompanyPresenter::present($row, 'public'))
            ->values()
            ->all();
    }

    public function listAdmin(Request $request): array
    {
        $q = $this->listingQuery();
        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }
        if ($request->query('recommended') === '1' || $request->query('is_recommended') === '1') {
            $q->where('is_recommended', true);
        }
        if ($request->query('designated') === '1' || $request->query('is_designated') === '1') {
            $q->where('is_designated', true);
        }

        return $q->limit(200)
            ->get()
            ->map(function (MakerCompany $row): array {
                $this->ensureUploadToken($row);

                return CompanyPresenter::present($row->fresh() ?? $row, 'admin');
            })
            ->values()
            ->all();
    }

    public function findAdmin(int $id): array
    {
        $row = MakerCompany::query()->findOrFail($id);
        $this->ensureUploadToken($row);

        return CompanyPresenter::present($row->fresh() ?? $row, 'admin');
    }

    public function storeAdmin(array $payload): array
    {
        $userId = (int) $payload['user_id'];
        if (MakerCompany::query()->where('user_id', $userId)->exists()) {
            throw new DomainException('해당 회원에게 이미 업체가 등록되어 있습니다.', 422);
        }

        $status = $payload['status'] ?? 'pending';
        $attrs = array_merge(CompanyRules::applicantAttributes($payload), $this->adminOnlyAttributes($payload, true));
        $attrs['user_id'] = $userId;
        $attrs['status'] = $status;
        $attrs['reviewed_at'] = in_array($status, ['approved', 'rejected'], true) ? now() : null;
        if ($status === 'rejected') {
            $attrs['rejected_reason'] = $payload['rejected_reason'] ?? $payload['note'] ?? null;
        }

        $this->assertBusinessNo($attrs);
            $row = MakerCompany::query()->create($attrs);
        $this->attachLogo($row, $userId, (string) ($payload['upload_token'] ?? ''));

        return CompanyPresenter::present($row->fresh() ?? $row, 'admin');
    }

    public function updateAdmin(int $id, array $payload): array
    {
        $row = MakerCompany::query()->findOrFail($id);
        $attrs = $this->adminOnlyAttributes($payload, false);
        foreach (['name', 'kind', 'business_no', 'homepage_url', 'portfolio_url', 'manager_name', 'phone', 'email', 'zipcode', 'address', 'address_detail', 'bio', 'note'] as $key) {
            if (! array_key_exists($key, $payload)) {
                continue;
            }
            $val = $payload[$key];
            if ($val === '' || $val === null) {
                continue; // blank/null means unchanged on partial admin update
            }
            $attrs[$key] = $val;
        }
        if (array_key_exists('job_types', $payload) || $this->hasJobTypeFlags($payload)) {
            $jobTypes = CompanyRules::collectJobTypes($payload);
            $attrs['job_types'] = $jobTypes;
            $attrs['type'] = $jobTypes[0] ?? ($payload['type'] ?? $row->type);
        } elseif (array_key_exists('type', $payload)) {
            $attrs['type'] = $payload['type'];
        }
        if (array_key_exists('kind', $payload)) {
            $attrs['kind'] = CompanyRules::normalizeKind($payload['kind']);
        }
        if (array_key_exists('status', $payload) && CompanyRules::isAllowedStatus((string) $payload['status'])) {
            $attrs['status'] = $payload['status'];
            if (in_array($payload['status'], ['approved', 'rejected', 'pending'], true)) {
                $attrs['reviewed_at'] = now();
            }
            if ($payload['status'] === 'approved') {
                $attrs['rejected_reason'] = null;
                $attrs['hold_reason'] = $payload['hold_reason'] ?? null;
            }
        }
        $this->assertBusinessNo($attrs, (int) $row->id);
        $row->fill($attrs);
        $row->save();
        $token = (string) ($payload['upload_token'] ?? $row->upload_token ?? '');
        if ($token === '') {
            $token = $this->ensureUploadToken($row);
        }
        $this->attachLogo($row, (int) $row->user_id, $token);

        return CompanyPresenter::present($row->fresh() ?? $row, 'admin');
    }

    public function approve(int $id): array
    {
        $row = MakerCompany::query()->findOrFail($id);
        if (! CompanyRules::canApprove($row->status)) {
            throw new DomainException('이미 승인된 업체입니다.', 422);
        }
        $row->status = 'approved';
        $row->rejected_reason = null;
        $row->hold_reason = null;
        $row->reviewed_at = now();
        $row->save();

        return CompanyPresenter::present($row, 'admin');
    }

    public function hold(int $id, array $payload): array
    {
        $row = MakerCompany::query()->findOrFail($id);
        $row->status = 'pending';
        $row->hold_reason = $payload['hold_reason'] ?? $payload['note'] ?? $row->hold_reason;
        if (array_key_exists('admin_memo', $payload)) {
            $row->admin_memo = $payload['admin_memo'];
        }
        $row->reviewed_at = now();
        $row->save();

        return CompanyPresenter::present($row, 'admin');
    }

    public function reject(int $id, array $payload): array
    {
        $row = MakerCompany::query()->findOrFail($id);
        if (! CompanyRules::canReject($row->status)) {
            throw new DomainException('이미 거절된 업체입니다.', 422);
        }
        $row->status = 'rejected';
        $row->rejected_reason = $payload['rejected_reason'] ?? $payload['note'] ?? $row->rejected_reason;
        if (isset($payload['note'])) {
            $row->note = $payload['note'];
        }
        if (array_key_exists('admin_memo', $payload)) {
            $row->admin_memo = $payload['admin_memo'];
        }
        $row->reviewed_at = now();
        $row->save();

        return CompanyPresenter::present($row, 'admin');
    }

    public function destroy(int $id): void
    {
        $row = MakerCompany::query()->findOrFail($id);
        MakerBid::query()->where('company_id', $row->id)->update(['company_id' => null]);
        $row->delete();
    }

    public function listingQuery()
    {
        return MakerCompany::query()
            ->orderByDesc('is_recommended')
            ->orderByDesc('priority')
            ->orderByDesc('id');
    }

    private function attachLogo(MakerCompany $row, int $userId, string $token): void
    {
        if ($token === '') {
            return;
        }
        $file = MakerJobFile::query()
            ->where('upload_token', $token)
            ->where('collection', UploadRules::COLLECTION_LOGOS)
            ->orderByDesc('id')
            ->first();
        if ($file) {
            // Prefer company owner as file owner for future member edits.
            if ((int) $file->user_id !== $userId && $userId > 0) {
                $file->user_id = $userId;
                $file->save();
            }
            $row->logo_hash = $file->hash;
            $row->upload_token = $token;
            $row->save();
        }
    }

    private function ensureUploadToken(MakerCompany $row): string
    {
        $token = trim((string) ($row->upload_token ?? ''));
        if ($token !== '') {
            return $token;
        }
        $token = $this->files->newUploadToken();
        $row->upload_token = $token;
        $row->save();

        return $token;
    }

    private function adminOnlyAttributes(array $payload, bool $creating): array
    {
        $out = [];
        if ($creating || array_key_exists('admin_memo', $payload)) {
            $out['admin_memo'] = $payload['admin_memo'] ?? null;
        }
        if ($creating || array_key_exists('hold_reason', $payload)) {
            $out['hold_reason'] = $payload['hold_reason'] ?? null;
        }
        if ($creating || array_key_exists('rating_score', $payload)) {
            $out['rating_score'] = CompanyRules::clampRatingScore($payload['rating_score'] ?? 0);
        }
        if ($creating || array_key_exists('rating_count', $payload)) {
            $out['rating_count'] = max(0, (int) ($payload['rating_count'] ?? 0));
        }
        if ($creating || array_key_exists('claim_count', $payload)) {
            $out['claim_count'] = max(0, (int) ($payload['claim_count'] ?? 0));
        }
        if ($creating || array_key_exists('claim_history', $payload)) {
            $out['claim_history'] = CompanyRules::normalizeClaimHistory($payload['claim_history'] ?? []);
        }
        if ($creating || array_key_exists('report_count', $payload)) {
            $out['report_count'] = max(0, (int) ($payload['report_count'] ?? 0));
        }
        if ($creating || array_key_exists('is_recommended', $payload)) {
            $out['is_recommended'] = CompanyRules::isTruthy($payload['is_recommended'] ?? false);
        }
        if ($creating || array_key_exists('is_designated', $payload)) {
            $out['is_designated'] = CompanyRules::isTruthy($payload['is_designated'] ?? false);
        }
        if ($creating || array_key_exists('priority', $payload)) {
            $out['priority'] = CompanyRules::clampPriority($payload['priority'] ?? 0);
        }

        return $out;
    }

    private function hasJobTypeFlags(array $payload): bool
    {
        foreach ($payload as $key => $_) {
            if (is_string($key) && str_starts_with($key, 'job_type_')) {
                return true;
            }
        }

        return false;
    }
    private function assertBusinessNo(array &$attrs, ?int $ignoreId = null): void
    {
        if (! array_key_exists('business_no', $attrs)) {
            return;
        }
        $raw = $attrs['business_no'];
        // Blank on update payload → leave unchanged (do not null out existing).
        if ($raw === null || $raw === '') {
            unset($attrs['business_no']);

            return;
        }
        $digits = CompanyRules::normalizeBusinessNo($raw);
        if ($digits !== null && $digits !== '' && ! CompanyRules::isValidBusinessNo($digits)) {
            throw new DomainException('사업자등록번호 형식이 올바르지 않습니다. (10자리)', 422);
        }
        if ($digits === null || $digits === '') {
            unset($attrs['business_no']);

            return;
        }
        $attrs['business_no'] = $digits;
        $q = MakerCompany::query()->where('business_no', $digits);
        if ($ignoreId) {
            $q->where('id', '!=', $ignoreId);
        }
        if ($q->exists()) {
            throw new DomainException('이미 등록된 사업자등록번호입니다.', 422);
        }
    }


}
