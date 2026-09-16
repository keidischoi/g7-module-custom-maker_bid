<?php

namespace Modules\Custom\MakerBids\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBids\Support\AwardRules;

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
