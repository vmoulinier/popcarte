<?php

namespace App\Tests\Service;

use App\Service\TwoFactorConfigService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TwoFactorConfigServiceTest extends TestCase
{
    private $logger;
    private $configService;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        
        $this->configService = new TwoFactorConfigService(
            'test_secret_key',
            'http://test.example.com',
            10,
            true,
            $this->logger
        );
    }

    public function testGetSsoSharedSecret()
    {
        $secret = $this->configService->getSsoSharedSecret();
        $this->assertEquals('test_secret_key', $secret);
    }

    public function testGetSymfonyBaseUrl()
    {
        $url = $this->configService->getSymfonyBaseUrl();
        $this->assertEquals('http://test.example.com', $url);
    }

    public function testGetHttpTimeout()
    {
        $timeout = $this->configService->getHttpTimeout();
        $this->assertEquals(10, $timeout);
    }

    public function testIsDebugMode()
    {
        $debugMode = $this->configService->isDebugMode();
        $this->assertTrue($debugMode);
    }

    public function testValidateSsoSecretWithValidToken()
    {
        $isValid = $this->configService->validateSsoSecret('test_secret_key');
        $this->assertTrue($isValid);
    }

    public function testValidateSsoSecretWithInvalidToken()
    {
        $isValid = $this->configService->validateSsoSecret('invalid_token');
        $this->assertFalse($isValid);
    }

    public function testValidateSsoSecretWithEmptyToken()
    {
        $isValid = $this->configService->validateSsoSecret('');
        $this->assertFalse($isValid);
    }

    public function testGetSsoLoginUrl()
    {
        $url = $this->configService->getSsoLoginUrl();
        $this->assertEquals('http://test.example.com/symfony/api/internal/sso/login', $url);
    }

    public function testGetSsoLogoutUrl()
    {
        $url = $this->configService->getSsoLogoutUrl();
        $this->assertEquals('http://test.example.com/symfony/api/internal/sso/logout', $url);
    }

    public function testGet2faManagementUrl()
    {
        $url = $this->configService->get2faManagementUrl();
        $this->assertEquals('/symfony/account/2fa', $url);
    }

    public function testGet2faLoginUrl()
    {
        $url = $this->configService->get2faLoginUrl();
        $this->assertEquals('/symfony/security/2fa/login', $url);
    }
} 
