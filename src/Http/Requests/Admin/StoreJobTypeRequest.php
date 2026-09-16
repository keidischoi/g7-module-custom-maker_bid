<?php

namespace Modules\Custom\MakerBids\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBids\Http\Concerns\FlattensValidationErrors;
use Modules\Custom\MakerBids\Support\BlankToNull;
use Modules\Custom\MakerBids\Support\BooleanishFields;
use Modules\Custom\MakerBids\Support\TypeRules;

class StoreJobTypeRequest extends FormRequest
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
        $this->nullBlankFields(['description']);
        $this->coerceBooleanFields(['requires_address', 'is_design_only', 'includes_modeling', 'is_enabled']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return TypeRules::storeRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return TypeRules::messages();
    }
}
