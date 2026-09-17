<?php

namespace Modules\Custom\MakerBids\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBids\Support\BlankToNull;

class UpdateBidRequest extends FormRequest
{
    use BlankToNull;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->liftNestedFormFields(['form', 'bid', 'edit']);
        // Partial bid update: blank days/message keep existing.
        $this->dropBlankKeys(['days', 'message', 'amount']);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['sometimes', 'integer', 'min:1'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
