<?php

namespace Modules\Custom\MakerBids\Support;

final class HistoryLayout
{
    public static function panel(): array
    {
        $rows = [];
        for ($i = 0; $i < 8; $i++) {
            $src = '{{viewer.data.my_bid_history.'.$i.' || bid_revisions.data.data.'.$i.' || bid_revisions.data.'.$i.'}}';
            $amt = '{{viewer.data.my_bid_history.'.$i.'.amount || bid_revisions.data.data.'.$i.'.amount || bid_revisions.data.'.$i.'.amount}}';
            $days = '{{viewer.data.my_bid_history.'.$i.'.days || bid_revisions.data.data.'.$i.'.days || "-"}}';
            $when = '{{viewer.data.my_bid_history.'.$i.'.created_at || bid_revisions.data.data.'.$i.'.created_at || ""}}';
            $msg = '{{viewer.data.my_bid_history.'.$i.'.message || ""}}';
            $rows[] = [
                'id' => 'cmb_bid_hist_r'.$i,
                'type' => 'basic',
                'name' => 'P',
                'if' => $amt,
                'text' => $amt.'원 / '.$days.'일 · '.$when.' '.$msg,
                'props' => [
                    'className' => 'text-sm rounded-lg border px-3 py-2',
                    'text' => $amt.'원 / '.$days.'일 · '.$when.' '.$msg,
                ],
            ];
        }

        return [
            'id' => 'cmb_bid_hist',
            'type' => 'basic',
            'name' => 'Div',
            'props' => ['className' => 'cmb-section-card space-y-2 rounded-xl border p-4 mt-3'],
            'children' => array_merge([
                ['id' => 'cmb_bid_hist_h', 'type' => 'basic', 'name' => 'H2', 'props' => ['text' => '내 견적 이력', 'className' => 'cmb-section-title text-sm font-semibold']],
            ], $rows),
        ];
    }
}
