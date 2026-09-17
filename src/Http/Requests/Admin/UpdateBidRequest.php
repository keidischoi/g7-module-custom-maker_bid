<?php

namespace Modules\Custom\MakerBids\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBids\Http\Concerns\FlattensValidationErrors;
use Modules\Custom\MakerBids\Support\BidRules;
use Modules\Custom\MakerBids\Support\BlankToNull;

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
        $this->dropBlankKeys(['days', 'message', 'amount', 'status']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return BidRules::adminUpdateRules();
    }
}
