<?php

namespace Tests\Unit;

use App\Models\Tenant;
use Tests\TestCase;

class TenantMailHelpersTest extends TestCase
{
    public function test_mail_enabled_defaults_true_and_require_smtp_defaults_false(): void
    {
        $tenant = new Tenant(['settings' => []]);

        $this->assertTrue($tenant->mailEnabled());
        $this->assertFalse($tenant->requireSmtp());
    }

    public function test_mail_enabled_and_require_smtp_read_from_settings(): void
    {
        $tenant = new Tenant(['settings' => [
            'notifications' => ['email_enabled' => false],
            'mail'          => ['require_smtp' => true],
        ]]);

        $this->assertFalse($tenant->mailEnabled());
        $this->assertTrue($tenant->requireSmtp());
    }

    public function test_smtp_credentials_returns_null_when_incomplete_and_array_when_complete(): void
    {
        $incomplete = new Tenant();
        $incomplete->mail_credentials = ['host' => 'smtp.test', 'username' => 'u']; // missing password/port
        $this->assertNull($incomplete->smtpCredentials());
        $this->assertFalse($incomplete->usesOwnSmtp());

        $complete = new Tenant(['settings' => []]);
        $complete->mail_credentials = [
            'host' => 'smtp.test', 'port' => 587, 'encryption' => 'tls',
            'username' => 'u', 'password' => 'p',
            'from_address' => 'club@test.com', 'from_name' => 'Club',
        ];
        $this->assertIsArray($complete->smtpCredentials());
        $this->assertSame('smtp.test', $complete->smtpCredentials()['host']);
        $this->assertTrue($complete->usesOwnSmtp());
    }
}
