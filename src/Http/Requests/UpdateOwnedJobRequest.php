<?php

namespace Modules\Custom\MakerBids\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBids\Http\Concerns\FlattensValidationErrors;
use Modules\Custom\MakerBids\Support\BlankToNull;
use Modules\Custom\MakerBids\Support\BooleanishFields;
use Modules\Custom\MakerBids\Support\JobRules;

class UpdateOwnedJobRequest extends FormRequest
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
        $this->liftNestedFormFields(['form']);
        $this->nullBlankFields([
            'description', 'budget', 'budget_min', 'budget_max', 'closes_at', 'rush_deadline',
            'size_w', 'size_d', 'size_h', 'revision_count', 'revision_cost', 'contact_hours',
            'zipcode', 'address', 'address_detail', 'upload_token',
            'manager_name', 'manager_phone', 'manager_email',
        ]);
        $this->coerceBooleanFields([
            'rush_fee_enabled', 'schedule_premium_enabled', 'revision_enabled', 'ownership_requested',
            'ext_stl', 'ext_3mf', 'ext_obj', 'ext_step', 'ext_stp', 'ext_gcode', 'ext_fbx', 'ext_dwg',
        ]);
        if ($this->exists('sizes')) {
            $this->merge(['sizes' => JobRules::decodeSizesInput($this->input('sizes'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return JobRules::memberUpdateRules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return JobRules::messages();
    }
}
