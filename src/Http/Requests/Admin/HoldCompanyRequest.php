<?php

namespace Modules\Custom\MakerBids\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBids\Http\Concerns\FlattensValidationErrors;
use Modules\Custom\MakerBids\Support\BlankToNull;
use Modules\Custom\MakerBids\Support\CompanyRules;

class HoldCompanyRequest extends FormRequest
{
    use BlankToNull;
    use FlattensValidationErrors;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->nullBlankFields(['hold_reason', 'admin_memo', 'note']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CompanyRules::holdRules();
    }
}
