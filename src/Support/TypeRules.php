<?php

namespace Modules\Custom\MakerBid\Support;

class TypeRules
{
    /**
     * @return array<string, list<string>>
     */
    public static function storeRules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]{1,62}$/'],
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'requires_address' => ['nullable', 'boolean'],
            'is_design_only' => ['nullable', 'boolean'],
            'is_enabled' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:99999'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function updateRules(): array
    {
        $rules = self::storeRules();
        $rules['slug'] = ['sometimes', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]{1,62}$/'];
        $rules['name'] = ['sometimes', 'string', 'max:120'];

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'slug.required' => '슬러그를 입력해 주세요.',
            'slug.regex' => '슬러그는 영문 소문자로 시작하고 영문·숫자·밑줄만 사용할 수 있습니다.',
            'name.required' => '유형 이름을 입력해 주세요.',
        ];
    }
}
