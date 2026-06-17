<?php

namespace Tests\Unit;

use App\Services\TwoFactor\TotpService;
use Tests\TestCase;

class TotpServiceTest extends TestCase
{
    public function test_secret_is_valid_base32(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();

        $this->assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        $this->assertGreaterThanOrEqual(16, strlen($secret));
    }

    public function test_verify_returns_true_for_current_window_code(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();

        // Compute the current code using the same algorithm
        $reflect = new \ReflectionMethod(TotpService::class, 'compute');
        $reflect->setAccessible(true);
        $time = (int) floor(time() / 30);
        $code = $reflect->invoke($totp, $secret, $time);

        $this->assertTrue($totp->verify($secret, $code));
    }

    public function test_verify_rejects_wrong_code(): void
    {
        $totp = new TotpService();
        $secret = $totp->generateSecret();

        $this->assertFalse($totp->verify($secret, '000000'));
        $this->assertFalse($totp->verify($secret, 'abcdef'));
        $this->assertFalse($totp->verify($secret, '12345')); // wrong length
    }

    public function test_provisioning_uri_format(): void
    {
        $totp = new TotpService();
        $secret = 'JBSWY3DPEHPK3PXP';
        $uri = $totp->provisioningUri($secret, 'user@example.com', 'CourtMaster');

        $this->assertStringStartsWith('otpauth://totp/', $uri);
        $this->assertStringContainsString('secret=' . $secret, $uri);
        $this->assertStringContainsString('issuer=CourtMaster', $uri);
        $this->assertStringContainsString('digits=6', $uri);
        $this->assertStringContainsString('period=30', $uri);
    }

    public function test_recovery_codes_are_unique_and_formatted(): void
    {
        $totp = new TotpService();
        $codes = $totp->generateRecoveryCodes(8);

        $this->assertCount(8, $codes);
        $this->assertCount(8, array_unique($codes));
        foreach ($codes as $c) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]{5}-[a-z0-9]{5}$/', $c);
        }
    }
}
