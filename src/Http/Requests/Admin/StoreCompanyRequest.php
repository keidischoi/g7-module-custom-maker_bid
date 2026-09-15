<?php

namespace Modules\Custom\MakerBid\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBid\Support\BlankToNull;
use Modules\Custom\MakerBid\Support\CompanyRules;

class StoreCompanyRequest extends FormRequest
{
    use BlankToNull;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->nullBlankFields(['type', 'note', 'status']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CompanyRules::adminStoreRules();
    }
}
