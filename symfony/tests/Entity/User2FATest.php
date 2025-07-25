<?php

namespace App\Tests\Entity;

use App\Entity\User2FA;
use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;

class User2FATest extends TestCase
{
    private $user2fa;

    protected function setUp(): void
    {
        $this->user2fa = new User2FA();
    }

    public function testDefaultValues()
    {
        $this->assertFalse($this->user2fa->isEnabled());
        $this->assertNotNull($this->user2fa->getCreatedAt());
        $this->assertNull($this->user2fa->getTempLoginSecret());
        $this->assertNull($this->user2fa->getTempLoginExpiresAt());
    }

    public function testSetAndGetUserId()
    {
        $userId = '123';
        $this->user2fa->setUserId($userId);
        $this->assertEquals($userId, $this->user2fa->getUserId());
    }

    public function testSetAndGetSecret()
    {
        $secret = 'TESTSECRET123';
        $this->user2fa->setSecret($secret);
        $this->assertEquals($secret, $this->user2fa->getSecret());
    }

    public function testSetAndGetEnabled()
    {
        $this->user2fa->setEnabled(true);
        $this->assertTrue($this->user2fa->isEnabled());

        $this->user2fa->setEnabled(false);
        $this->assertFalse($this->user2fa->isEnabled());
    }

    public function testSetAndGetCreatedAt()
    {
        $createdAt = new \DateTimeImmutable('2023-01-01 12:00:00');
        $this->user2fa->setCreatedAt($createdAt);
        $this->assertEquals($createdAt, $this->user2fa->getCreatedAt());
    }

    public function testSetAndGetTempLoginSecret()
    {
        $token = 'test_token_123';
        $this->user2fa->setTempLoginSecret($token);
        $this->assertEquals($token, $this->user2fa->getTempLoginSecret());

        $this->user2fa->setTempLoginSecret(null);
        $this->assertNull($this->user2fa->getTempLoginSecret());
    }

    public function testSetAndGetTempLoginExpiresAt()
    {
        $expiresAt = new \DateTimeImmutable('2023-01-01 13:00:00');
        $this->user2fa->setTempLoginExpiresAt($expiresAt);
        $this->assertEquals($expiresAt, $this->user2fa->getTempLoginExpiresAt());

        $this->user2fa->setTempLoginExpiresAt(null);
        $this->assertNull($this->user2fa->getTempLoginExpiresAt());
    }

    public function testIsTotpAuthenticationEnabled()
    {
        $this->user2fa->setEnabled(true);
        $this->assertTrue($this->user2fa->isTotpAuthenticationEnabled());

        $this->user2fa->setEnabled(false);
        $this->assertFalse($this->user2fa->isTotpAuthenticationEnabled());
    }

    public function testGetTotpAuthenticationUsername()
    {
        $userId = '123';
        $this->user2fa->setUserId($userId);
        $this->assertEquals($userId, $this->user2fa->getTotpAuthenticationUsername());
    }

    public function testGetTotpAuthenticationConfiguration()
    {
        $secret = 'TESTSECRET123';
        $this->user2fa->setSecret($secret);

        $config = $this->user2fa->getTotpAuthenticationConfiguration();
        
        $this->assertInstanceOf(TotpConfiguration::class, $config);
        $this->assertEquals($secret, $config->getSecret());
        $this->assertEquals(TotpConfiguration::ALGORITHM_SHA1, $config->getAlgorithm());
        $this->assertEquals(30, $config->getPeriod());
        $this->assertEquals(6, $config->getDigits());
    }

    public function testTokenExpiration()
    {
        $token = 'test_token';
        $expiresAt = new \DateTimeImmutable('+5 minutes');
        
        $this->user2fa->setTempLoginSecret($token);
        $this->user2fa->setTempLoginExpiresAt($expiresAt);

        $this->assertEquals($token, $this->user2fa->getTempLoginSecret());
        $this->assertEquals($expiresAt, $this->user2fa->getTempLoginExpiresAt());
        $this->assertGreaterThan(new \DateTimeImmutable(), $this->user2fa->getTempLoginExpiresAt());
    }

    public function testClearToken()
    {
        $this->user2fa->setTempLoginSecret('test_token');
        $this->user2fa->setTempLoginExpiresAt(new \DateTimeImmutable('+5 minutes'));

        $this->user2fa->setTempLoginSecret(null);
        $this->user2fa->setTempLoginExpiresAt(null);

        $this->assertNull($this->user2fa->getTempLoginSecret());
        $this->assertNull($this->user2fa->getTempLoginExpiresAt());
    }
} 
