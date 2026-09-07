<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * An in-app alert that a connection needs attention — its authentication
 * expired, or it was auto-disabled after repeated failures. Written to the
 * database channel and surfaced in the notification bell. Kept synchronous (a
 * quick DB write) so it lands the moment the failure is recorded.
 */
class IntegrationFailed extends Notification
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $url = null,
        public ?string $icon = 'plug',
    ) {}

    /**
     * @return array<int, string>
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
        return [
            'title' => $this->title,
            'body' => $this->body,
            'url' => $this->url,
            'icon' => $this->icon,
        ];
    }
}
