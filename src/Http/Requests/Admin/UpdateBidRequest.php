<?php

namespace Modules\Custom\MakerBid\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBid\Http\Concerns\FlattensValidationErrors;
use Modules\Custom\MakerBid\Support\BidRules;
use Modules\Custom\MakerBid\Support\BlankToNull;

class UpdateBidRequest extends FormRequest
{
    use BlankToNull;
    use FlattensValidationErrors;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->liftNestedFormFields(['form', 'edit']);
        $this->nullBlankFields(['days', 'message']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return BidRules::adminUpdateRules();
    }
}
