<?php

namespace Modules\Custom\MakerBids\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Custom\MakerBids\Http\Concerns\FlattensValidationErrors;
use Modules\Custom\MakerBids\Support\BlankToNull;
use Modules\Custom\MakerBids\Support\BidRules;
use Modules\Custom\MakerBids\Support\BooleanishFields;
use Modules\Custom\MakerBids\Support\SettingsRules;

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
            'nav_js_enabled', 'extension_user_base', 'extension_home', 'guests_see_list', 'require_confirmed',
        ];
        foreach (array_keys(SettingsRules::pages()) as $page) {
            $bools[] = $page.'_enabled';
        }
        $this->coerceBooleanFields($bools);
        $this->nullBlankFields(array_merge(
            ['nav_label', 'nav_insert', 'default_job_status', 'bid_allow', 'provided_extensions',
                'method', 'destination', 'bank_name', 'account_no', 'account_holder', 'transfer_note', 'instructions'],
            array_map(static fn (string $page): string => $page.'_body', array_keys(SettingsRules::pages())),
        ));
        if ($this->exists('bid_allow')) {
            $this->merge(['bid_allow' => BidRules::normalizeAllow($this->input('bid_allow'))]);
        }
        if ($this->exists('method')) {
            $this->merge(['method' => \Modules\Custom\MakerBids\Support\PaymentRules::normalizeMethod($this->input('method'))]);
        }
        if ($this->exists('destination')) {
            $this->merge(['destination' => \Modules\Custom\MakerBids\Support\PaymentRules::normalizeDestination($this->input('destination'))]);
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
