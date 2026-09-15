<?php

namespace Modules\Custom\MakerBid\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBid\Support\BidRules;
use Modules\Custom\MakerBid\Support\BlankToNull;

class UpdateBidRequest extends FormRequest
{
    use BlankToNull;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->nullBlankFields(['days', 'message']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return BidRules::writeRules();
    }
}
