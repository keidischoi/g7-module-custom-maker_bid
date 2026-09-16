<?php

namespace Modules\Custom\MakerBids\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBids\Http\Concerns\FlattensValidationErrors;
use Modules\Custom\MakerBids\Support\BlankToNull;
use Modules\Custom\MakerBids\Support\BooleanishFields;
use Modules\Custom\MakerBids\Support\JobRules;

class UpdateJobRequest extends FormRequest
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
        $this->liftNestedFormFields(['form', 'edit']);
        $this->coerceSlugFields(['type', 'status', 'audience']);
        $this->nullBlankFields([
            'description', 'budget', 'budget_min', 'budget_max', 'closes_at', 'rush_deadline',
            'size_w', 'size_d', 'size_h', 'revision_count', 'revision_cost', 'contact_hours',
            'zipcode', 'address', 'address_detail',
            'manager_name', 'manager_phone', 'manager_email', 'sizes_json',
        ]);
        $this->coerceBooleanFields([
            'rush_fee_enabled', 'schedule_premium_enabled', 'revision_enabled', 'ownership_requested',
            'ext_stl', 'ext_3mf', 'ext_obj', 'ext_step', 'ext_stp', 'ext_gcode', 'ext_fbx', 'ext_dwg',
        ]);
        if ($this->exists('sizes_json') && ! $this->exists('sizes')) {
            $this->merge(['sizes' => JobRules::decodeSizesInput($this->input('sizes_json'))]);
        }
        if ($this->exists('sizes')) {
            $this->merge(['sizes' => JobRules::decodeSizesInput($this->input('sizes'))]);
        }
    }

    public function rules(): array
    {
        return JobRules::adminUpdateRules();
    }

    public function messages(): array
    {
        return JobRules::messages();
    }
}
