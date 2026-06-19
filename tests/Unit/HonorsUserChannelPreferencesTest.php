<?php

namespace Tests\Unit;

use App\Notifications\Concerns\HonorsUserChannelPreferences;
use Tests\TestCase;

class HonorsUserChannelPreferencesTest extends TestCase
{
    /** Build an anonymous notifiable stub with the given attributes. */
    private function notifiable(array $attrs = []): object
    {
        return (object) array_merge([
            'name'                     => 'Test',
            'email'                    => 'real@example.com',
            'phone'                    => null,
            'notification_preferences' => null,
        ], $attrs);
    }

    private function resolver(): object
    {
        return new class {
            use HonorsUserChannelPreferences;

            public function channels(object $notifiable, array $allowed = ['in_app', 'email', 'sms', 'push']): array
            {
                return $this->channelsForUser($notifiable, $allowed);
            }
        };
    }

    public function test_real_email_gets_mail_channel(): void
    {
        $channels = $this->resolver()->channels($this->notifiable());

        $this->assertContains('mail', $channels);
    }

    public function test_walk_in_placeholder_email_does_not_get_mail_channel(): void
    {
        $channels = $this->resolver()->channels(
            $this->notifiable(['email' => 'walkin@tenant1.local'])
        );

        $this->assertNotContains('mail', $channels);
        // Other channels still resolve normally.
        $this->assertContains('database', $channels);
    }

    public function test_any_dot_local_address_is_treated_as_non_deliverable(): void
    {
        $channels = $this->resolver()->channels(
            $this->notifiable(['email' => 'someone@club.LOCAL'])
        );

        $this->assertNotContains('mail', $channels);
    }

    public function test_empty_email_does_not_get_mail_channel(): void
    {
        $channels = $this->resolver()->channels($this->notifiable(['email' => null]));

        $this->assertNotContains('mail', $channels);
    }
}
