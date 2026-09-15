<?php

namespace Modules\Custom\MakerBid\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBid\Support\BlankToNull;
use Modules\Custom\MakerBid\Support\JobRules;

class UpdateJobRequest extends FormRequest
{
    use BlankToNull;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->nullBlankFields(['description', 'budget', 'closes_at']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return JobRules::adminUpdateRules();
    }
}
