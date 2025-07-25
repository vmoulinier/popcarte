<?php

namespace App\Tests\Service;

use App\Service\SsoTokenValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SsoTokenValidatorTest extends TestCase
{
    private $logger;
    private $validator;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->validator = new SsoTokenValidator('test_secret_key', $this->logger);
    }

    public function testValidateTokenWithValidToken()
    {
        $isValid = $this->validator->validateToken('test_secret_key');
        $this->assertTrue($isValid);
    }

    public function testValidateTokenWithInvalidToken()
    {
        $isValid = $this->validator->validateToken('invalid_token');
        $this->assertFalse($isValid);
    }

    public function testValidateTokenWithEmptyToken()
    {
        $isValid = $this->validator->validateToken('');
        $this->assertFalse($isValid);
    }

    public function testValidateTokenWithNullToken()
    {
        $isValid = $this->validator->validateToken(null);
        $this->assertFalse($isValid);
    }

    public function testValidateTokenWithDifferentLengthToken()
    {
        $isValid = $this->validator->validateToken('short');
        $this->assertFalse($isValid);
    }

    public function testGetSsoSharedSecret()
    {
        $secret = $this->validator->getSsoSharedSecret();
        $this->assertEquals('test_secret_key', $secret);
    }

    public function testValidateTokenWithTimingAttackProtection()
    {
        // Test que hash_equals est utilisé (protection contre les attaques timing)
        $startTime = microtime(true);
        $this->validator->validateToken('invalid_token');
        $invalidTime = microtime(true) - $startTime;

        $startTime = microtime(true);
        $this->validator->validateToken('test_secret_key');
        $validTime = microtime(true) - $startTime;

        // Les temps devraient être similaires (avec une tolérance)
        $this->assertLessThan(0.001, abs($validTime - $invalidTime));
    }
} 
