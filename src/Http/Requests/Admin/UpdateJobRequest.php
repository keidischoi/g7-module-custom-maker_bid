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
        $this->coerceSlugFields([
            'type', 'status', 'audience', 'title', 'description',
            'contact_name', 'contact_phone', 'contact_hours', 'contact_email',
            'zipcode', 'address', 'address_detail',
            'manager_name', 'manager_phone', 'manager_email',
        ]);
        $this->coerceBooleanFields([
            'rush_fee_enabled', 'schedule_premium_enabled', 'revision_enabled', 'ownership_requested',
            'ext_stl', 'ext_3mf', 'ext_obj', 'ext_step', 'ext_stp', 'ext_gcode', 'ext_fbx', 'ext_dwg',
        ]);
        $this->dropBlankKeys([
            'type', 'status', 'audience', 'title', 'description',
            'budget', 'budget_min', 'budget_max', 'closes_at', 'rush_deadline',
            'size_w', 'size_d', 'size_h', 'revision_count', 'revision_cost', 'contact_hours',
            'contact_hours_from', 'contact_hours_to',
            'contact_name', 'contact_phone', 'contact_email',
            'zipcode', 'address', 'address_detail', 'upload_token',
            'manager_name', 'manager_phone', 'manager_email', 'sizes_json',
        ]);
        if ($this->exists('sizes_json') && ! $this->exists('sizes')) {
            $decoded = JobRules::decodeSizesInput($this->input('sizes_json'));
            if ($decoded === [] || $decoded === null || $decoded === '') {
                $this->offsetUnset('sizes_json');
            } else {
                $this->merge(['sizes' => $decoded]);
            }
        }
        $this->normalizeJobStatusAndExtensions();

        if ($this->exists('sizes')) {
            $decoded = JobRules::decodeSizesInput($this->input('sizes'));
            if ($decoded === [] || $decoded === null || $decoded === '') {
                $this->offsetUnset('sizes');
            } else {
                $this->merge(['sizes' => $decoded]);
            }
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
