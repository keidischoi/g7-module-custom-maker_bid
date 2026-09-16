<?php

namespace Modules\Custom\MakerBids\Http\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

trait FlattensValidationErrors
{
    protected function failedValidation(Validator $validator): void
    {
        $messages = [];
        foreach ($validator->errors()->all() as $message) {
            if (! in_array($message, $messages, true)) {
                $messages[] = $message;
            }
        }
        $first = $messages[0] ?? '입력값이 올바르지 않습니다.';

        throw new HttpResponseException(response()->json([
            'message' => $first,
            'errors' => $validator->errors(),
        ], 422));
    }
}
