<?php

namespace Modules\Custom\MakerBids\Support;

class AwardRules
{
    public static function canAward(int $actorId, mixed $ownerId): bool
    {
        if ($ownerId === null || $ownerId === '') {
            return false;
        }

        return $actorId > 0 && (int) $ownerId === $actorId;
    }

    /**
     * @return array<string, list<string>>
     */
    public static function requestRules(): array
    {
        return [
            'bid_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @param  list<array{id:int,status:string}>  $bids
     * @return list<array{id:int,status:string}>
     */
    public static function apply(int $acceptedBidId, array $bids): array
    {
        $out = [];
        foreach ($bids as $bid) {
            $id = (int) $bid['id'];
            $out[] = [
                'id' => $id,
                'status' => $id === $acceptedBidId ? 'accepted' : 'rejected',
            ];
        }

        return $out;
    }
}
