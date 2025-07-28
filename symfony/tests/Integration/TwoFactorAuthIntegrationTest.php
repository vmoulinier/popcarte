<?php

namespace App\Tests\Integration;

use App\Entity\User2FA;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Test d'intégration simplifié pour le flux 2FA
 * 
 * Tests basiques qui fonctionnent sans configuration complexe
 */
class TwoFactorAuthIntegrationTest extends WebTestCase
{
    private $client;
    private $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
    }

    protected static function getKernelClass(): string
    {
        return \App\Kernel::class;
    }

    /**
     * Test que la page de gestion 2FA redirige vers le legacy
     */
    public function test2FAManagementPageRedirectsToLegacy()
    {
        $this->client->request('GET', '/account/2fa');
        
        // La page devrait rediriger vers le legacy
        $this->assertResponseStatusCodeSame(302);
        $this->assertResponseRedirects('/Web/index.php');
    }

    /**
     * Test que la page de validation 2FA redirige vers le legacy
     */
    public function test2FALoginPageRedirectsToLegacy()
    {
        $this->client->request('GET', '/security/2fa/login');
        
        // La page devrait rediriger vers le legacy
        $this->assertResponseStatusCodeSame(302);
        $this->assertResponseRedirects('/Web/index.php');
    }

    /**
     * Test de création d'un utilisateur 2FA en mémoire
     */
    public function testUser2FAEntityCreation()
    {
        // Créer un utilisateur 2FA en mémoire
        $user2fa = new User2FA();
        $user2fa->setUserId('test_user_123');
        $user2fa->setSecret('TESTSECRET123');
        $user2fa->setEnabled(false);
        
        // Vérifier que l'utilisateur a été configuré correctement
        $this->assertEquals('test_user_123', $user2fa->getUserId());
        $this->assertEquals('TESTSECRET123', $user2fa->getSecret());
        $this->assertFalse($user2fa->isEnabled());
        
        // Activer la 2FA
        $user2fa->setEnabled(true);
        $this->assertTrue($user2fa->isEnabled());
    }

    /**
     * Test de génération de jeton de connexion temporaire
     */
    public function testTemporaryLoginTokenGeneration()
    {
        // Créer un utilisateur 2FA en mémoire
        $user2fa = new User2FA();
        $user2fa->setUserId('test_user_456');
        $user2fa->setSecret('TESTSECRET456');
        $user2fa->setEnabled(true);
        
        // Générer un jeton temporaire
        $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $user2fa->setTempLoginSecret($token);
        $user2fa->setTempLoginExpiresAt(new \DateTimeImmutable('+5 minutes'));
        
        // Vérifier que le jeton a été généré
        $this->assertNotNull($user2fa->getTempLoginSecret());
        $this->assertNotNull($user2fa->getTempLoginExpiresAt());
        $this->assertGreaterThan(new \DateTimeImmutable(), $user2fa->getTempLoginExpiresAt());
        $this->assertEquals($token, $user2fa->getTempLoginSecret());
    }

    /**
     * Test de configuration TOTP
     */
    public function testTotpConfiguration()
    {
        $user2fa = new User2FA();
        $user2fa->setUserId('test_user_789');
        $user2fa->setSecret('TESTSECRET789');
        $user2fa->setEnabled(true);
        
        // Vérifier la configuration TOTP
        $this->assertTrue($user2fa->isTotpAuthenticationEnabled());
        $this->assertEquals('test_user_789', $user2fa->getTotpAuthenticationUsername());
        
        $config = $user2fa->getTotpAuthenticationConfiguration();
        $this->assertEquals('TESTSECRET789', $config->getSecret());
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }
} 
