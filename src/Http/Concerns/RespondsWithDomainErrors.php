<?php

namespace Modules\Custom\MakerBids\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Modules\Custom\MakerBids\Support\DomainException;

trait RespondsWithDomainErrors
{
    protected function domainError(DomainException $e): JsonResponse
    {
        $payload = ['message' => $e->getMessage()];
        if ($e->errors() !== []) {
            $payload['errors'] = $e->errors();
        }

        return response()->json($payload, $e->status());
    }
}
