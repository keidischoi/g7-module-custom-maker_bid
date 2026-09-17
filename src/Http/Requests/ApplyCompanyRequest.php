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
        $this->nullBlankFields([
            'type', 'note', 'bio', 'business_no', 'homepage_url', 'portfolio_url',
            'manager_name', 'phone', 'email', 'zipcode', 'address', 'address_detail',
            'kind', 'upload_token',
        ]);
        if ($this->exists('kind')) {
            $this->merge(['kind' => CompanyRules::normalizeKind($this->input('kind'))]);
        }
        $this->dropBlankKeys(['type']);
        $this->merge(['job_types' => CompanyRules::collectJobTypes($this->all())]);
        $this->offsetUnset('is_designated');
        $this->offsetUnset('designated');
    }

    public function rules(): array
    {
        return CompanyRules::applyRules();
    }

    public function messages(): array
    {
        return CompanyRules::messages();
    }
}
