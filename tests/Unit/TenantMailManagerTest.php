<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\TenantMailManager;
use Tests\TestCase;

class TenantMailManagerTest extends TestCase
{
    private function manager(): TenantMailManager
    {
        // Pin a known platform default regardless of phpunit env.
        config(['mail.default' => 'smtp']);
        return new TenantMailManager();
    }

    public function test_disabled_email_resolves_to_array_discard(): void
    {
        $tenant = new Tenant(['settings' => ['notifications' => ['email_enabled' => false]]]);

        $this->assertSame('array', $this->manager()->resolveMailerFor($tenant));
        $this->assertFalse($this->manager()->wouldDeliver($tenant));
    }

    public function test_no_credentials_falls_back_to_platform_default(): void
    {
        $tenant = new Tenant(['settings' => []]);

        $this->assertSame('smtp', $this->manager()->resolveMailerFor($tenant));
        $this->assertTrue($this->manager()->wouldDeliver($tenant));
    }

    public function test_complete_credentials_register_and_return_tenant_smtp(): void
    {
        $tenant = new Tenant(['settings' => []]);
        $tenant->mail_credentials = [
            'host' => 'smtp.mailtrap.io', 'port' => 587, 'encryption' => 'tls',
            'username' => 'user', 'password' => 'secret',
            'from_address' => 'club@test.com', 'from_name' => 'Test Club',
        ];

        $manager = $this->manager();
        $this->assertSame('tenant_smtp', $manager->resolveMailerFor($tenant));

        // The dynamic mailer config was registered from the credentials.
        $this->assertSame('smtp.mailtrap.io', config('mail.mailers.tenant_smtp.host'));
        $this->assertSame(587, config('mail.mailers.tenant_smtp.port'));
        $this->assertSame('club@test.com', config('mail.from.address'));
    }
}
