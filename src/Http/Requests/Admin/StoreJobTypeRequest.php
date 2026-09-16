<?php

namespace Modules\Custom\MakerBid\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBid\Http\Concerns\FlattensValidationErrors;
use Modules\Custom\MakerBid\Support\BlankToNull;
use Modules\Custom\MakerBid\Support\BooleanishFields;
use Modules\Custom\MakerBid\Support\TypeRules;

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
        $this->coerceBooleanFields(['requires_address', 'is_design_only', 'is_enabled']);
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
