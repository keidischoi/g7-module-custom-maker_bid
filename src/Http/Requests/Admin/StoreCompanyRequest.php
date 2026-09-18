<?php

namespace Modules\Custom\MakerBids\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBids\Http\Concerns\FlattensValidationErrors;
use Modules\Custom\MakerBids\Support\BlankToNull;
use Modules\Custom\MakerBids\Support\BooleanishFields;
use Modules\Custom\MakerBids\Support\CompanyRules;

class StoreCompanyRequest extends FormRequest
{
    use BlankToNull;
    use BooleanishFields;
    use FlattensValidationErrors;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->liftNestedFormFields(['company', 'edit', 'form']);
        $this->nullBlankFields(['type', 'note', 'status', 'kind', 'bio', 'admin_memo', 'hold_reason']);
        $this->coerceSlugFields([
            'name', 'kind', 'type', 'bio', 'note', 'business_no',
            'homepage_url', 'portfolio_url', 'manager_name', 'phone', 'email',
            'zipcode', 'address', 'address_detail',
            'bank_name', 'account_no', 'account_holder', 'deposit_terms',
        ]);
        $this->coerceBooleanFields(['is_recommended', 'is_designated']);
        if ($this->exists('kind')) {
            $this->merge(['kind' => CompanyRules::normalizeKind($this->input('kind'))]);
        }
        if ($this->exists('job_types') || $this->hasJobTypeFlags()) {
            $this->merge(['job_types' => CompanyRules::collectJobTypes($this->all())]);
        }
        if ($this->exists('claim_history')) {
            $this->merge(['claim_history' => CompanyRules::normalizeClaimHistory($this->input('claim_history'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CompanyRules::adminStoreRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CompanyRules::messages();
    }

    private function hasJobTypeFlags(): bool
    {
        foreach ($this->all() as $key => $_) {
            if (is_string($key) && str_starts_with($key, 'job_type_')) {
                return true;
            }
        }

        return false;
    }
}
