<?php

namespace Modules\Custom\MakerBid\Http\Concerns;

use Illuminate\Http\JsonResponse;
use Modules\Custom\MakerBid\Support\DomainException;

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
