<?php

namespace Modules\Custom\MakerBid\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBid\Http\Concerns\FlattensValidationErrors;
use Modules\Custom\MakerBid\Support\BlankToNull;
use Modules\Custom\MakerBid\Support\BooleanishFields;
use Modules\Custom\MakerBid\Support\CompanyRules;

class UpdateCompanyRequest extends FormRequest
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
        $this->nullBlankFields(['type', 'note', 'status', 'kind', 'bio', 'admin_memo', 'hold_reason', 'rejected_reason']);
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
        return CompanyRules::adminUpdateRules();
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
