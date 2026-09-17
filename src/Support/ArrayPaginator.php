<?php

namespace Modules\Custom\MakerBids\Support;

use Illuminate\Http\Request;

final class ArrayPaginator
{
    /**
     * Slice a list into a page and return `{ data, meta }` for public list APIs.
     *
     * @param  list<mixed>  $items
     * @return array{data: list<mixed>, meta: array{total: int, page: int, per_page: int, last_page: int}}
     */
    public static function paginate(array $items, Request $request, string $pageKey = 'page', int $defaultPerPage = 10): array
    {
        $page = max(1, (int) $request->query($pageKey, 1));
        $per = min(50, max(5, (int) $request->query('per_page', $defaultPerPage)));
        $total = count($items);
        $last = max(1, (int) ceil($total / max(1, $per)));
        if ($page > $last) {
            $page = $last;
        }
        $offset = ($page - 1) * $per;
        $slice = array_values(array_slice($items, $offset, $per));

        return [
            'data' => $slice,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $per,
                'last_page' => $last,
            ],
        ];
    }
}
