<?php

namespace Modules\Custom\MakerBid\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBid\Support\BlankToNull;
use Modules\Custom\MakerBid\Support\CompanyRules;

class ApplyCompanyRequest extends FormRequest
{
    use BlankToNull;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->nullBlankFields(['type', 'note']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CompanyRules::applyRules();
    }
}
