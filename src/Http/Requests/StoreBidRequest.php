<?php

namespace Modules\Custom\MakerBids\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBids\Support\BidRules;
use Modules\Custom\MakerBids\Support\BlankToNull;

class StoreBidRequest extends FormRequest
{
    use BlankToNull;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->nullBlankFields(['days', 'message']);
    }

    public function rules(): array
    {
        return BidRules::writeRules();
    }
}
