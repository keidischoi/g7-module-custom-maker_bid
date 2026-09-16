<?php

namespace Modules\Custom\MakerBid\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBid\Http\Concerns\FlattensValidationErrors;
use Modules\Custom\MakerBid\Support\BlankToNull;
use Modules\Custom\MakerBid\Support\BidRules;
use Modules\Custom\MakerBid\Support\BooleanishFields;
use Modules\Custom\MakerBid\Support\SettingsRules;

class UpdateSettingsRequest extends FormRequest
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
        $this->liftNestedFormFields(['form']);
        $bools = [
            'nav_js_enabled', 'extension_user_base', 'extension_home', 'guests_see_list',
        ];
        foreach (array_keys(SettingsRules::pages()) as $page) {
            $bools[] = $page.'_enabled';
        }
        $this->coerceBooleanFields($bools);
        $this->nullBlankFields(array_merge(
            ['nav_label', 'nav_insert', 'default_job_status', 'bid_allow'],
            array_map(static fn (string $page): string => $page.'_body', array_keys(SettingsRules::pages())),
        ));
        if ($this->exists('bid_allow')) {
            $this->merge(['bid_allow' => BidRules::normalizeAllow($this->input('bid_allow'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return SettingsRules::adminUpdateRules();
    }
}
