<?php

namespace Modules\Custom\MakerBids\Support;

final class HistoryLayout
{
    public static function panel(): array
    {
        return [
            'id' => 'cmb_bid_hist',
            'type' => 'basic',
            'name' => 'Div',
            'props' => ['className' => 'cmb-section-card space-y-2 rounded-xl border p-4 mt-3'],
            'children' => [
                ['id' => 'cmb_bid_hist_h', 'type' => 'basic', 'name' => 'H2', 'props' => ['text' => '내 견적 이력', 'className' => 'cmb-section-title text-sm font-semibold']],
                [
                    'id' => 'cmb_bid_hist_row',
                    'type' => 'basic',
                    'name' => 'Div',
                    'props' => [
                        'className' => 'text-sm rounded-lg border px-3 py-2 cmb-list-item cmb-job-card',
                        'iteration' => [
                            'source' => '{{bid_revisions?.data?.data ?? bid_revisions?.data ?? []}}',
                            'item_var' => '$item',
                        ],
                    ],
                    'children' => [
                        ['id' => 'cmb_bid_hist_line', 'type' => 'basic', 'name' => 'P', 'text' => '{{$item.event_label || "제출"}} · {{$item.amount}}원 · {{$item.days || "-"}}일'],
                        ['id' => 'cmb_bid_hist_meta', 'type' => 'basic', 'name' => 'P', 'props' => [
                            'className' => 'text-xs text-gray-500',
                            'text' => '{{$item.created_at}} {{$item.message || ""}}',
                        ]],
                    ],
                ],
            ],
        ];
    }
}
