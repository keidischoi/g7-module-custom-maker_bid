<?php

namespace Modules\Custom\MakerBids\Support;

class NoticeRules
{
    public const TYPE = 'maker_bids.notice';

    public const HOOK_PREFIX = 'custom-maker_bids';

    public static function clickUrl(?int $jobId): string
    {
        if ($jobId !== null && $jobId > 0) {
            return '/maker-bids/'.$jobId;
        }

        return '/maker-bids/notices';
    }

    /**
     * Payload stored in G7 `notifications.data` (homepage bell).
     *
     * @return array<string, mixed>
     */
    public static function databasePayload(string $event, string $title, ?string $body, ?int $jobId): array
    {
        $url = self::clickUrl($jobId);
        $subject = function_exists('mb_substr') ? mb_substr($title, 0, 200) : substr($title, 0, 200);

        return [
            'type' => self::TYPE,
            'subject' => $subject,
            'body' => (string) $body,
            'click_url' => $url,
            'data' => [
                'event' => $event,
                'title' => $title,
                'body' => $body,
                'job_id' => $jobId,
                'action_url' => $url,
            ],
        ];
    }
}
