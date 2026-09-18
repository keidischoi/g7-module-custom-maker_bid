<?php

namespace Modules\Custom\MakerBids\Notifications;

use Illuminate\Notifications\Notification;

/**
 * Writes into Laravel/G7 `notifications` so the homepage bell can show it.
 * Subject/body are stored at the top level of `data`, matching UserNotificationResource.
 */
class MakerBidsDatabaseNotification extends Notification
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(private readonly array $payload) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return $this->payload;
    }
}
