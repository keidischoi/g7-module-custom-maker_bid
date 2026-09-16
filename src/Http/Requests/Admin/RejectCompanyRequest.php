<?php

namespace Modules\Custom\MakerBids\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBids\Support\BlankToNull;
use Modules\Custom\MakerBids\Support\CompanyRules;

class RejectCompanyRequest extends FormRequest
{
    use BlankToNull;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->nullBlankFields(['note', 'rejected_reason', 'admin_memo']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CompanyRules::rejectRules();
    }
}
