<?php

namespace Modules\Custom\MakerBid\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBid\Support\AwardRules;

class AwardJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return AwardRules::requestRules();
    }
}
