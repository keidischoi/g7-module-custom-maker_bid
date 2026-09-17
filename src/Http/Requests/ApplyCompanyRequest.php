<?php

namespace Modules\Custom\MakerBids\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBids\Http\Concerns\FlattensValidationErrors;
use Modules\Custom\MakerBids\Support\BlankToNull;
use Modules\Custom\MakerBids\Support\CompanyRules;

class ApplyCompanyRequest extends FormRequest
{
    use BlankToNull;
    use FlattensValidationErrors;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->liftNestedFormFields(['company', 'form']);
        $this->coerceSlugFields([
            'name', 'kind', 'type', 'bio', 'note', 'business_no',
            'homepage_url', 'portfolio_url', 'manager_name', 'phone', 'email',
            'zipcode', 'address', 'address_detail',
        ]);
        // Profile PATCH (incl. logo-only): blank = unchanged, not clear.
        $this->dropBlankKeys([
            'name', 'kind', 'type', 'note', 'bio', 'business_no', 'homepage_url', 'portfolio_url',
            'manager_name', 'phone', 'email', 'zipcode', 'address', 'address_detail',
            'upload_token',
        ]);
        if ($this->exists('kind')) {
            $this->merge(['kind' => CompanyRules::normalizeKind($this->input('kind'))]);
        }
        $hasJobTypeFlags = false;
        foreach ($this->all() as $key => $_) {
            if (is_string($key) && str_starts_with($key, 'job_type_')) {
                $hasJobTypeFlags = true;
                break;
            }
        }
        if ($this->exists('job_types') || $hasJobTypeFlags) {
            $this->merge(['job_types' => CompanyRules::collectJobTypes($this->all())]);
        }
        $this->offsetUnset('is_designated');
        $this->offsetUnset('designated');
    }

    public function rules(): array
    {
        $user = $this->user();
        if ($user) {
            try {
                if (\Modules\Custom\MakerBids\Models\MakerCompany::query()->where('user_id', (int) $user->id)->exists()) {
                    return CompanyRules::applyUpdateRules();
                }
            } catch (\Throwable) {
            }
        }

        return CompanyRules::applyRules();
    }

    public function messages(): array
    {
        return CompanyRules::messages();
    }
}
