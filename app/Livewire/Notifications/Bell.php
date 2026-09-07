<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * The topbar notification bell: a live unread count and a dropdown of recent
 * in-app notifications (integration failures for now). Polls so the count
 * updates without a page load; marking one read navigates to the affected page.
 */
class Bell extends Component
{
    public function markAllRead(): void
    {
        auth()->user()?->unreadNotifications->markAsRead();
    }

    public function open(string $id): mixed
    {
        $user = auth()->user();
        $notification = $user?->notifications()->whereKey($id)->first();
        $notification?->markAsRead();

        $url = $notification?->data['url'] ?? null;

        if (is_string($url) && $url !== '') {
            return $this->redirect($url, navigate: true);
        }

        return null;
    }

    public function render(): mixed
    {
        $user = auth()->user();

        /** @var Collection<int, DatabaseNotification> $recent */
        $recent = $user !== null ? $user->notifications()->latest()->limit(10)->get() : new Collection;
        $unread = $user !== null ? $user->unreadNotifications()->count() : 0;

        return view('livewire.notifications.bell', [
            'recent' => $recent,
            'unread' => $unread,
        ]);
    }
}
