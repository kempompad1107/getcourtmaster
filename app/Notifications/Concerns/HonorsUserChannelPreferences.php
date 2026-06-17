<?php

namespace App\Notifications\Concerns;

/**
 * Resolves notification channels from a user's notification_preferences JSON.
 * Default prefs (when nothing is stored):
 *   email   => true
 *   sms     => false
 *   push    => true
 *   in_app  => true   (database channel)
 *
 * Per-notification opt-out is supported by passing a "channel keys" list:
 *   $this->channelsForUser($notifiable, ['email', 'in_app']);
 */
trait HonorsUserChannelPreferences
{
    protected function channelsForUser(object $notifiable, array $allowed = ['in_app', 'email', 'sms', 'push']): array
    {
        $prefs = $notifiable->notification_preferences ?? [];
        $channels = [];

        if (in_array('in_app', $allowed, true) && ($prefs['in_app'] ?? true)) {
            $channels[] = 'database';
        }
        if (in_array('email', $allowed, true) && ($prefs['email'] ?? true)) {
            $channels[] = 'mail';
        }
        if (in_array('sms', $allowed, true) && ($prefs['sms'] ?? false) && !empty($notifiable->phone ?? null)) {
            $channels[] = 'sms';
        }
        if (in_array('push', $allowed, true) && ($prefs['push'] ?? true)) {
            $channels[] = 'webpush';
        }

        return $channels;
    }
}
