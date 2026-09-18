<?php

namespace Modules\Custom\MakerBids\Support;

final class HistoryLayout
{
    public static function panel(): array
    {
        $children = [
            ['id' => 'cmb_bid_hist_h', 'type' => 'basic', 'name' => 'H2', 'text' => '내 견적 이력', 'props' => ['text' => '내 견적 이력', 'className' => 'cmb-section-title']],
        ];
        foreach ([0, 1, 2, 3, 4] as $i) {
            $label = '{{viewer.data.my_bid_history.'.$i.'.amount_label || viewer.data.my_bid_history.'.$i.'.amount}}';
            $days = '{{viewer.data.my_bid_history.'.$i.'.days}}';
            $when = '{{viewer.data.my_bid_history.'.$i.'.created_at}}';
            $children[] = [
                'id' => 'cmb_bid_hist_r'.$i,
                'type' => 'basic',
                'name' => 'P',
                'text' => $label.' · '.$days.'일 · '.$when,
                'props' => [
                    'className' => 'text-sm rounded-lg border px-3 py-2',
                    'text' => $label.' · '.$days.'일 · '.$when,
                ],
            ];
        }

        return [
            'id' => 'cmb_bid_hist',
            'type' => 'basic',
            'name' => 'Div',
            'props' => ['className' => 'cmb-section-card space-y-2 rounded-xl border p-4 mt-3'],
            'children' => $children,
        ];
    }
}
