<?php

namespace Modules\Custom\MakerBid\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBid\Http\Concerns\FlattensValidationErrors;
use Modules\Custom\MakerBid\Support\BlankToNull;
use Modules\Custom\MakerBid\Support\CompanyRules;

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
        $this->nullBlankFields([
            'type', 'note', 'bio', 'business_no', 'homepage_url', 'portfolio_url',
            'manager_name', 'phone', 'email', 'zipcode', 'address', 'address_detail',
            'kind', 'upload_token',
        ]);
        if ($this->exists('kind')) {
            $this->merge(['kind' => CompanyRules::normalizeKind($this->input('kind'))]);
        }
        $this->merge(['job_types' => CompanyRules::collectJobTypes($this->all())]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CompanyRules::applyRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return CompanyRules::messages();
    }
}
